<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use pmmp\encoding\ByteBuffer;
use function array_shift;
use function count;
use function min;
use function strlen;

final class SendQueue
{
    private array $queue = [];
    private int $mss = Protocol::MIN_PACKET_SIZE;
    private ByteBuffer $pk;

    public function __construct()
    {
        $this->pk = new ByteBuffer();
        $this->pk->reserve(Protocol::MAX_PACKET_SIZE);
    }

    public function available(): bool
    {
        return count($this->queue) > 0 || $this->pk->getUsedLength() > 0;
    }

    public function getMSS(): int
    {
        return $this->mss;
    }

    public function setMSS(int $mss): void
    {
        $this->mss = $mss;
    }

    public function add(string $payload): void
    {
        $this->queue[] = $payload;
    }

    public function pack(int $window): ?string
    {
        if (count($this->queue) === 0 && $this->pk->getUsedLength() === 0) {
            return null;
        }

        $size = min($window, $this->mss);
        while (count($this->queue) > 0) {
            $entry = $this->queue[0];
            if ($this->pk->getUsedLength() + strlen($entry) > $size) {
                break;
            }
            array_shift($this->queue);
            $this->pk->writeByteArray($entry);
        }
        return $this->pk->toString();
    }

    public function flush(): void
    {
        $this->pk->clear();
        $this->pk->setReadOffset(0);
        $this->pk->setWriteOffset(0);
    }

    public function clear(): void
    {
        $this->pk->clear();
        $this->pk->trim();
        $this->queue = [];
    }
}
