<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\ConnectionRequest;
use cooldogedev\spectral\frame\ConnectionResponse;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\StreamRequest;
use cooldogedev\spectral\frame\StreamResponse;

final class ServerConnection extends Connection
{
    /**
     * @var null|Closure(Stream $stream): void
     */
    private ?Closure $streamAcceptor = null;

    /**
     * @param null|Closure(Stream $stream): void $streamAcceptor
     */
    public function setStreamAcceptor(?Closure $streamAcceptor): void
    {
        $this->streamAcceptor = $streamAcceptor;
    }

    private function acceptStream(StreamRequest $request): void
    {
        $stream = $this->createStream($request->streamID);
        if ($stream === null) {
            $this->write(StreamResponse::create($request->streamID, StreamResponse::STREAM_RESPONSE_FAILED));
            return;
        }

        $this->write(StreamResponse::create($request->streamID, StreamResponse::STREAM_RESPONSE_SUCCESS));
        if ($this->streamAcceptor !== null) {
            ($this->streamAcceptor)($stream);
        }
    }

    public function handle(Frame $fr): void
    {
        match (true) {
            $fr instanceof ConnectionRequest => $this->write(ConnectionResponse::create($this->connectionID, ConnectionResponse::CONNECTION_RESPONSE_SUCCESS)),
            $fr instanceof StreamRequest => $this->acceptStream($fr),
            default => null,
        };
        parent::handle($fr);
    }
}
