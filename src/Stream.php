<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\frame\StreamData;
use cooldogedev\spectral\util\log\Logger;
use function str_split;

final class Stream
{
    private int $sequenceID = 0;

    /**
     * @var array<int, Closure(string $payload): void>
     */
    private array $readers = [];
    /**
     * @var array<int, Closure(): void>
     */
    private array $closeHandlers = [];

    private FrameQueue $frameQueue;

    private bool $closed = false;

    public function __construct(
        private readonly SendQueue  $sendQueue,
        private readonly Logger     $logger,
        private readonly int        $streamID,
    ) {
        $this->frameQueue = new FrameQueue();
    }

    /**
     * @param Closure(string $payload): void $reader
     */
    public function registerReader(Closure $reader): void
    {
        $this->readers[] = $reader;
    }

    public function registerCloseHandler(Closure $handler): void
    {
        $this->closeHandlers[] = $handler;
    }

    public function write(string $p): void
    {
        if ($this->closed) {
            return;
        }

        $fr = StreamData::create($this->streamID, 0, "");
        $payload = str_split($p, $this->sendQueue->getMSS() - 20);
        foreach ($payload as $chunk) {
            $fr->sequenceID = $this->sequenceID;
            $fr->payload = $chunk;
            $this->sendQueue->add(Pack::packSingle($fr));
            $this->sequenceID++;
        }
    }

    public function close(): void
    {
        $this->logger->log("stream_close_application", "streamID", $this->streamID);
        $this->internalClose();
    }

    public function internalClose(): void
    {
        if (!$this->closed) {
            foreach ($this->closeHandlers as $closeHandler) {
                $closeHandler();
            }
            $this->readers = [];
            $this->closeHandlers = [];
            $this->closed = true;
            $this->frameQueue->clear();
            $this->logger->log("stream_close", "streamID", $this->streamID);
        }
    }

    public function receive(StreamData $fr): void
    {
        if ($this->closed) {
            return;
        }

        $this->frameQueue->enqueue($fr->payload, $fr->sequenceID);
        while (($payload = $this->frameQueue->dequeue()) !== null) {
            foreach ($this->readers as $reader) {
                $reader($payload);
            }
        }
    }
}
