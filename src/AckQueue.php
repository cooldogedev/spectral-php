<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

final class AckQueue
{
    private int $max = 0;
    private int $maxTime = 0;
    private int $nextAck = 0;

    /**
     * @var array<int>
     */
    private array $queue = [];

    public function add(int $now, int $sequenceID): void
    {
        $this->queue[] = $sequenceID;
        if ($sequenceID > $this->max) {
            $this->max = $sequenceID;
            $this->maxTime = $now;
        }

        if ($this->nextAck === 0) {
            $this->nextAck = $now + (Protocol::MAX_ACK_DELAY - Protocol::TIMER_GRANULARITY);
        }
    }

    /**
     * @return array{0: array<int>, 1: int, 2: int}|null
     */
    public function flush(int $now): ?array
    {
        if (count($this->queue) === 0 || $this->nextAck > $now) {
            return null;
        }
        $queue = $this->queue;
        $max = $this->max;
        $delay = $now - $this->maxTime;
        $this->queue = [];
        $this->max = 0;
        $this->maxTime = 0;
        $this->nextAck = 0;
        return [$queue, $max, $delay];
    }

    public function clear(): void
    {
        $this->queue = [];
    }
}
