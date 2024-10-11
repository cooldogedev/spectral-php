<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\frame\Pack;
use pmmp\encoding\ByteBuffer;
use function array_shift;
use function count;
use function strlen;

final class SendQueue
{
    private const PACKET_SIZE = Protocol::MAX_PACKET_SIZE - Protocol::PACKET_HEADER_SIZE;

    private int $sequenceID = 0;
    private int $total = 0;

    private ByteBuffer $buf;

    /**
     * @var array<int, string>
     */
    private array $queue = [];

    public function __construct(public int $connectionID)
    {
        $this->buf = new ByteBuffer();
        $this->buf->reserve(SendQueue::PACKET_SIZE);
    }

    public function add(string $payload): void
    {
        $this->queue[] = $payload;
    }

    public function pack(): ?int
    {
        if ($this->total > 0) {
            return $this->buf->getUsedLength();
        }

        if (count($this->queue) === 0) {
            return null;
        }

        while (count($this->queue) > 0) {
            if ($this->buf->getUsedLength() + strlen($this->queue[0]) > SendQueue::PACKET_SIZE) {
                break;
            }
            $this->total++;
            $this->buf->writeByteArray(array_shift($this->queue));
        }
        return $this->buf->getUsedLength();
    }

    public function flush(): ?array
    {
        if ($this->total === 0) {
            return null;
        }
        $this->sequenceID++;
        $packet = Pack::pack($this->connectionID, $this->sequenceID, $this->total, $this->buf->toString());
        $this->total = 0;
        $this->buf->clear();
        $this->buf->setWriteOffset(0);
        return [$this->sequenceID, $packet];
    }
}
