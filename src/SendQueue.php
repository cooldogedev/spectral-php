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
    private int $total = 0;
    private int $mss = MTUDiscovery::MTU_MIN;
    private ByteBuffer $pk;

    public function __construct()
    {
        $this->pk = new ByteBuffer();
        $this->pk->reserve(MTUDiscovery::MTU_MAX);
    }

    public function available(): bool
    {
        return count($this->queue) > 0 || $this->total > 0;
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

    public function pack(int $window): ?array
    {
        if (count($this->queue) === 0 && $this->total === 0) {
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
            $this->total++;
        }
        return [$this->total, $this->pk->toString()];
    }

    public function flush(): void
    {
        $this->pk->clear();
        $this->pk->setReadOffset(0);
        $this->pk->setWriteOffset(0);
        $this->total = 0;
    }

    public function clear(): void
    {
        $this->pk->clear();
        $this->pk->trim();
        $this->queue = [];
    }
}
