<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\congestion\RTT;
use cooldogedev\spectral\congestion\Sender;
use cooldogedev\spectral\frame\Acknowledgement;
use cooldogedev\spectral\frame\ConnectionClose;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\MTURequest;
use cooldogedev\spectral\frame\MTUResponse;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\frame\StreamClose;
use cooldogedev\spectral\frame\StreamData;
use cooldogedev\spectral\util\Address;
use cooldogedev\spectral\util\log\Logger;
use cooldogedev\spectral\util\Time;
use function floor;
use function strlen;

abstract class Connection
{
    private const CONNECTION_ACTIVITY_TIMEOUT = Time::SECOND * 30;

    protected readonly AckQueue $ack;
    protected readonly Sender $sender;
    protected readonly ReceiveQueue $receiveQueue;
    protected readonly RetransmissionQueue $retransmission;
    protected readonly SendQueue $sendQueue;
    protected readonly StreamMap $streams;
    protected readonly RTT $rtt;
    protected readonly MTUDiscovery $discovery;

    public readonly Logger $logger;

    protected int $sequenceID = 0;
    protected int $idle = 0;

    protected bool $closed = false;

    public function __construct(protected readonly Conn $conn, public int $connectionID, int $perspective)
    {
        $now = Time::unixNano();
        $this->logger = Logger::create($perspective);
        $this->ack = new AckQueue();
        $this->sender = new Sender($this->logger, $now, Protocol::MIN_PACKET_SIZE);
        $this->receiveQueue = new ReceiveQueue();
        $this->retransmission = new RetransmissionQueue();
        $this->sendQueue = new SendQueue();
        $this->streams = new StreamMap();
        $this->rtt = new RTT();
        $this->discovery = new MTUDiscovery(function (int $mtu): void {
            $this->sender->setMSS($mtu);
            $this->sendQueue->setMSS($mtu);
            $this->logger->log("mtu_update", "new", $mtu);
        });
        $this->idle = $now + Connection::CONNECTION_ACTIVITY_TIMEOUT;
    }

    public function getLocalAddress(): Address
    {
        return $this->conn->localAddress;
    }

    public function getRemoteAddress(): Address
    {
        return $this->conn->remoteAddress;
    }

    public function closeWithError(int $code, string $message): void
    {
        $this->logger->log("connection_close_err", "code", $code, "message", $message);
        $this->writeControl(ConnectionClose::create($code, $message), true);
        $this->internalClose();
    }

    public function tick(): bool
    {
        if ($this->closed) {
            return false;
        }

        $now = Time::unixNano();
        if ($now >= $this->idle) {
            $this->closeWithError(ConnectionClose::CONNECTION_CLOSE_TIMEOUT, "network inactivity");
            return false;
        }
        $this->retransmit($now);
        $this->maybeSend($now);
        return true;
    }

    /**
     * @internal
     * @param array<int, Frame> $frames
     */
    public function receive(int $sequenceID, array $frames): void
    {
        $now = Time::unixNano();
        if ($sequenceID !== 0) {
            $this->ack->add($now, $sequenceID);
            if (!$this->receiveQueue->add($sequenceID)) {
                $this->logger->log("duplicate_receive", "sequenceID", $sequenceID);
                return;
            }
        }

        foreach ($frames as $frame) {
            $this->handle($now, $frame);
        }
        $this->idle = $now + Connection::CONNECTION_ACTIVITY_TIMEOUT;
    }

    /**
     * @internal
     */
    public function handle(int $now, Frame $fr): void
    {
        if ($fr instanceof Acknowledgement) {
            foreach ($fr->ranges as [$start, $end]) {
                for ($i = $start; $i <= $end; $i++) {
                    $entry = $this->retransmission->remove($i);
                    if ($entry !== null) {
                        if ($i === $fr->max) {
                            $this->rtt->add($now - $entry->sent, Time::MICROSECOND * $fr->delay);
                        }
                        $this->sender->onAck($now, $entry->sent, $this->rtt, strlen($entry->payload) - Protocol::PACKET_HEADER_SIZE);
                    }
                }
            }
        }

        if ($fr instanceof StreamClose) {
            $this->logger->log("stream_close_request", "streamID", $fr->streamID);
            $this->streams->get($fr->streamID)?->internalClose();
        }

        match (true) {
            $fr instanceof ConnectionClose => $this->internalClose(),
            $fr instanceof StreamData => $this->streams->get($fr->streamID)?->receive($fr),
            $fr instanceof MTURequest => $this->writeControl(MTUResponse::create($fr->mtu)),
            $fr instanceof MTUResponse => $this->discovery->onAck($fr->mtu),
            default => null,
        };
    }

    private function maybeSend(int $now): void
    {
        if (!$this->discovery->discovered && $this->discovery->sendProbe($now, $this->rtt->getSRTT())) {
            $this->writeControl(MTURequest::create($this->discovery->current));
            $this->logger->log("mtu_probe", "new", $this->discovery->current);
        }

        while ($this->sendQueue->available()) {
            if (!$this->transmit($now)) {
                break;
            }
        }
        $this->acknowledge($now);
    }

    private function transmit(int $now): bool
    {
        $available = $this->sender->getAvailable();
        if ($available === 0) {
            $this->logger->log("congestion_block", "window", $available);
            return false;
        }

        $payload = $this->sendQueue->pack($available);
        $length = $payload !== null ? strlen($payload) : 0;
        if ($length === 0) {
            $this->logger->log("congestion_block", "len", $length, "window", $available);
            return false;
        }

        if ($this->sender->getTimeUntilSend($now, $this->rtt, $length) > $now) {
            $this->logger->log("pacer_block", "len", $length, "window", $available);
            return false;
        }
        $this->sequenceID++;
        $pk = Pack::pack($this->connectionID, $this->sequenceID, $payload);
        $this->conn->write($this->appendAcknowledgements($now, $pk));
        $this->sendQueue->flush();
        $this->sender->onSend($length);
        $this->retransmission->add($now, $this->sequenceID, $pk);
        return true;
    }

    private function appendAcknowledgements(int $now, string $p): string
    {
        $total = (int)floor(($this->sendQueue->getMSS() - strlen($p) - 16) / 8);
        $ack = $this->ack->flush($now, $total, true);
        if ($ack !== null) {
            [$ranges, $max, $delay] = $ack;
            return $p . Pack::packSingle(Acknowledgement::create($delay, $max, $ranges));
        }
        return $p;
    }

    private function acknowledge(int $now): void
    {
        while (($ack = $this->ack->flush($now, Protocol::MAX_ACK_RANGES, false)) !== null) {
            [$ranges, $max, $delay] = $ack;
            $this->writeControl(Acknowledgement::create($delay, $max, $ranges));
        }
    }

    private function retransmit(int $now): void
    {
        $entry = $this->retransmission->shift($now, $this->rtt->getRTO());
        if ($entry !== null) {
            [$sentTime, $payload] = $entry;
            $this->sender->onCongestionEvent($now, $sentTime);
            $this->conn->write($payload);
        }
    }

    /**
     * @internal
     */
    public function writeControl(Frame $fr, bool $needsAck = false): void
    {
        $payload = Pack::packSingle($fr);
        $sequenceID = 0;
        if ($needsAck) {
            $this->sequenceID++;
            $sequenceID = $this->sequenceID;
        }

        $pk = Pack::pack($this->connectionID, $sequenceID, $payload);
        if ($needsAck) {
            $this->retransmission->add(Time::unixNano(), $sequenceID, $pk);
        }
        $this->conn->write($pk);
    }

    protected function createStream(int $streamID): ?Stream
    {
        if ($this->streams->get($streamID) !== null) {
            $this->logger->log("duplicate_stream", "streamID", $streamID);
            return null;
        }
        $stream = new Stream($this->sendQueue, $this->logger, $streamID);
        $stream->registerCloseHandler(function () use ($streamID): void {
            $this->streams->remove($this->sequenceID);
            $this->writeControl(StreamClose::create($streamID), true);
        });
        $this->streams->add($stream, $streamID);
        return $stream;
    }

    private function internalClose(): void
    {
        if ($this->closed) {
            return;
        }

        foreach ($this->streams->all() as $stream) {
            $stream->internalClose();
        }
        $this->discovery->mtuIncrease = null;
        $this->logger->log("connection_close");
        $this->logger->close();
        $this->closed = true;
        $this->ack->clear();
        $this->receiveQueue->clear();
        $this->retransmission->clear();
        $this->sendQueue->clear();
        $this->conn->close();
    }
}
