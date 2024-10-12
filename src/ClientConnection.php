<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\ConnectionResponse;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\frame\StreamRequest;
use cooldogedev\spectral\frame\StreamResponse;
use function socket_recvfrom;
use function socket_select;
use const MSG_DONTWAIT;
use const MSG_WAITALL;

final class ClientConnection extends Connection
{
    public ?int $connectionResponse = null;

    /**
     * @var array<int, Closure(Stream|null $stream): void>
     */
    private array $streamResponses = [];
    private int $streamID = 0;

    /**
     * @param null|Closure(Stream|null $stream): void $onResponse
     */
    public function openStream(?Closure $onResponse): void
    {
        $this->streamResponses[$this->streamID] = $onResponse ?? static fn () => null;
        $this->write(StreamRequest::create($this->streamID));
        $this->streamID++;
    }

    public function tick(): bool
    {
        if (!$this->closed) {
            $this->read();
        }
        return parent::tick();
    }

    private function read(): void
    {
        $read = [$this->conn->socket];
        $write = null;
        $except = null;
        $changed = @socket_select($read, $write, $except, 0, 50);
        if ($changed === 0) {
            return;
        }

        $bytes = "";
        $address = "";
        $port = "";
        $received = @socket_recvfrom($this->conn->socket, $bytes, 1500, Utils::getOS() !== Utils::OS_WINDOWS ? MSG_DONTWAIT : MSG_WAITALL, $address, $port);
        if ($received === false || $received === 0) {
            return;
        }

        $packet = Pack::unpack($bytes);
        if ($packet === null) {
            return;
        }
        [, $sequenceID, $frames] = $packet;
        $this->receive($sequenceID, $frames);
    }

    public function handle(Frame $fr): void
    {
        if ($fr instanceof ConnectionResponse) {
            $this->sendQueue->connectionID = $fr->connectionID;
            $this->connectionID = $fr->connectionID;
            $this->connectionResponse = $fr->response;
        }

        if ($fr instanceof StreamResponse) {
            $responseHandler = $this->streamResponses[$fr->streamID] ?? null;
            if ($responseHandler !== null) {
                if ($fr->response === ConnectionResponse::CONNECTION_RESPONSE_SUCCESS) {;
                    $responseHandler($this->createStream($fr->streamID));
                } else {
                    $responseHandler(null);
                }
                unset($this->streamResponses[$fr->streamID]);
            }
        }
        parent::handle($fr);
    }
}
