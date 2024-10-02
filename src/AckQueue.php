<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function count;

final class AckQueue
{
    private int $lastAck = 0;

    /**
     * @var array<int>
     */
    private array $queue = [];

    public function add(int $sequenceID): void
    {
        $this->lastAck = Utils::unixNano();
        $this->queue[] = $sequenceID;
    }

    /**
     * @return null|array{0: int, 1: array<int>}
     */
    public function all(): ?array
    {
        if (count($this->queue) === 0) {
            return null;
        }
        $queue = $this->queue;
        $this->queue = [];
        return [Utils::unixNano() - $this->lastAck, $queue];
    }
}
