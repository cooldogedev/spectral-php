<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function count;
use function sort;

final class AckQueue
{
    private int $sequenceID = 0;
    private int $lastAck = 0;

    private bool $sort = false;

    /**
     * @var array<int>
     */
    private array $queue = [];

    public function add(int $sequenceID): void
    {
        $this->sequenceID++;
        if (!$this->sort && $sequenceID !== $this->sequenceID) {
            $this->sort = true;
        }
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
        if ($this->sort) {
            sort($queue);
        }
        $this->sort = false;
        $this->queue = [];
        return [Utils::unixNano() - $this->lastAck, $queue];
    }
}
