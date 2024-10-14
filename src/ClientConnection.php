<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\ConnectionResponse;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\frame\StreamRequest;
use cooldogedev\spectral\frame\StreamResponse;
use cooldogedev\spectral\util\log\Perspective;
use cooldogedev\spectral\util\OS;
use function socket_recvfrom;
use function socket_select;
use const MSG_DONTWAIT;

final class ClientConnection extends Connection
{
    public ?int $connectionResponse = null;

    /**
     * @var array<int, Closure(Stream|null $stream): void>
     */
    private array $streamResponses = [];
    private int $streamID = 0;

    public function __construct(Conn $conn)
    {
        parent::__construct($conn, -1, Perspective::PERSPECTIVE_CLIENT);
    }

    /**
     * @param null|Closure(Stream|null $stream): void $onResponse
     */
    public function openStream(?Closure $onResponse): void
    {
        $streamID = $this->streamID;
        $this->streamID++;
        $this->logger->log("stream_open_request", "streamID", $streamID);
        $this->streamResponses[$streamID] = $onResponse ?? static fn () => null;
        $this->writeControl(StreamRequest::create($streamID), true);
    }

    public function tick(): bool
    {
        for ($i = 0; $i < 100 && !$this->closed; $i++) {
            if (!$this->read()) {
                break;
            }
        }
        return parent::tick();
    }

    private function read(): bool
    {
        $read = [$this->conn->socket];
        $write = null;
        $except = null;
        $changed = @socket_select($read, $write, $except, 0, 50);
        if ($changed === false || $changed === 0) {
            return false;
        }

        $bytes = "";
        $address = "";
        $port = "";
        $received = @socket_recvfrom($this->conn->socket, $bytes, 1500, OS::getOS() !== OS::OS_WINDOWS ? MSG_DONTWAIT : 0, $address, $port);
        if ($received === false || $received === 0) {
            return false;
        }

        $packet = Pack::unpack($bytes);
        if ($packet === null) {
            return false;
        }
        [, $sequenceID, $frames] = $packet;
        $this->receive($sequenceID, $frames);
        return true;
    }

    public function handle(int $now, Frame $fr): void
    {
        if ($fr instanceof ConnectionResponse) {
            $this->logger->setConnectionID($fr->connectionID);
            $this->connectionID = $fr->connectionID;
            $this->connectionResponse = $fr->response;
        }

        if ($fr instanceof StreamResponse) {
            $responseHandler = $this->streamResponses[$fr->streamID] ?? null;
            if ($responseHandler !== null) {
                if ($fr->response === ConnectionResponse::CONNECTION_RESPONSE_SUCCESS) {;
                    $this->logger->log("stream_open_success", "streamID", $fr->streamID);
                    $responseHandler($this->createStream($fr->streamID));
                } else {
                    $this->logger->log("stream_open_fail", "streamID", $fr->streamID);
                    $responseHandler(null);
                }
                unset($this->streamResponses[$fr->streamID]);
            } else {
                $this->logger->log("stream_response_unknown", "streamID", $fr->streamID);
            }
        }
        parent::handle($now, $fr);
    }
}
