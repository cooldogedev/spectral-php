<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\congestion\Cubic;
use cooldogedev\spectral\congestion\Pacer;
use cooldogedev\spectral\frame\Acknowledgement;
use cooldogedev\spectral\frame\ConnectionClose;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\frame\StreamClose;
use cooldogedev\spectral\frame\StreamData;
use function count;
use function floor;
use function strlen;
use function time;

abstract class Connection
{
    protected readonly AckQueue $ack;
    protected readonly Cubic $congestionController;
    protected readonly Pacer $pacer;
    protected readonly RetransmissionQueue $retransmission;
    protected readonly SendQueue $sendQueue;
    protected readonly StreamMap $streams;
    protected readonly RTT $rtt;

    protected int $lastActivity = 0;
    protected int $lastTick = 0;

    /**
     * @var array<int, int>
     */
    protected array $received = [];
    protected bool $closed = false;

    public function __construct(protected readonly Conn $conn, public int $connectionID)
    {
        $this->ack = new AckQueue();
        $this->congestionController = new Cubic();
        $this->pacer = new Pacer(10_000, 20);
        $this->retransmission = new RetransmissionQueue();
        $this->sendQueue = new SendQueue($this->connectionID);
        $this->streams = new StreamMap();
        $this->rtt = new RTT();
        $this->lastActivity = Utils::unixMilli();
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
        $this->write(ConnectionClose::create($code, $message));
        $this->internalClose();
    }

    public function tick(): bool
    {
        if ($this->closed) {
            return false;
        }

        $now = Utils::unixMilli();
        if ($now - $this->lastTick >= 80) {
            if ($now - $this->lastActivity > 30_000) {
                $this->closeWithError(ConnectionClose::CONNECTION_CLOSE_TIMEOUT, "closed due to inactivity");
                return false;
            }

            $this->acknowledge();
            $this->retransmit();
        }
        while ($this->transmit()) {}
        return true;
    }

    /**
     * @internal
     * @param array<int, Frame> $frames
     */
    public function receive(int $sequenceID, array $frames): void
    {
        if (isset($this->received[$sequenceID])) {
            return;
        }

        foreach ($frames as $frame) {
            $this->handle($frame);
        }

        if ($sequenceID !== 0) {
            $this->ack->add($sequenceID);
            $this->received[$sequenceID] = time();
        }
        $this->lastActivity = Utils::unixMilli();
    }

    /**
     * @internal
     */
    public function handle(Frame $fr): void
    {
        if ($fr instanceof Acknowledgement) {
            foreach ($fr->ranges as $i => [$start, $end]) {
                for ($j = $start; $j <= $end; $j++) {
                    $entry = $this->retransmission->remove($j);
                    if ($entry !== null) {
                        $this->congestionController->onAck(strlen($entry->payload));
                        if ($i === count($fr->ranges) - 1 && $j === $end) {
                            $this->rtt->add((int)floor((Utils::unixNano() - $entry->timestamp - $fr->delay) / 1000));
                        }
                    }
                }
            }

            if ($fr->type !== Acknowledgement::ACKNOWLEDGEMENT_WITH_GAPS) {
                foreach (Acknowledgement::generateAcknowledgementGaps($fr->ranges) as $sequenceID) {
                    $this->retransmission->nack($sequenceID);
                }
            }
        }

        match (true) {
            $fr instanceof ConnectionClose => $this->internalClose(),
            $fr instanceof StreamData => $this->streams->get($fr->streamID)?->receive($fr),
            $fr instanceof StreamClose => $this->streams->get($fr->streamID)?->internalClose(),
            default => null,
        };
    }

    /**
     * @internal
     */
    public function write(Frame $fr): void
    {
        $this->sendQueue->add(Pack::packSingle($fr));
    }

    protected function writeImmediately(Frame $fr): void
    {
        $this->conn->write(Pack::pack($this->connectionID, 0, 1, Pack::packSingle($fr)));
    }

    protected function createStream(int $streamID): ?Stream
    {
        if ($this->streams->get($streamID) !== null) {
            return null;
        }
        $stream = new Stream($this, $this->streams, $streamID);
        $this->streams->add($stream, $streamID);
        return $stream;
    }

    private function transmit(): bool
    {
        if ($this->sendQueue->isEmpty()) {
            return false;
        }

        $packet = $this->sendQueue->compute();
        $size = strlen($packet) + Protocol::PACKET_HEADER_SIZE;
        if (!$this->congestionController->onSend($size)) {
            return false;
        }

        $this->pacer->setInterval((int)floor($this->rtt->get() / $this->congestionController->getCwnd()));
        if (!$this->pacer->consume($size)) {
            return false;
        }
        [$sequenceID, $pk] = $this->sendQueue->flush();
        $this->retransmission->add($sequenceID, $pk);
        $this->conn->write($pk);
        return !$this->sendQueue->isEmpty();
    }

    private function acknowledge(): void
    {
        $result = $this->ack->all();
        if ($result === null) {
            return;
        }
        [$delay, $queue] = $result;
        [$type, $ranges] = Acknowledgement::generateAcknowledgementRange($queue);
        $this->writeImmediately(Acknowledgement::create($type, $delay, $ranges));
    }

    private function retransmit(): void
    {
        $entry = $this->retransmission->shift();
        if ($entry !== null) {
            $this->congestionController->onLoss(strlen($entry));
            $this->conn->write($entry);
        }
    }

    private function internalClose(): void
    {
        if ($this->closed) {
            return;
        }

        foreach ($this->streams->all() as $stream) {
            $stream->internalClose();
        }
        $this->closed = true;
        $this->conn->close();
    }
}
