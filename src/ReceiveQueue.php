<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

final class ReceiveQueue
{
    private int $expected = 1;
    /**
     * @var array<int, bool>
     */
    private array $queue = [];

    public function add(int $sequenceID): bool
    {
        if ($this->exists($sequenceID)) {
            return false;
        }
        $this->queue[$sequenceID] = true;
        $this->merge();
        return true;
    }

    public function clear(): void
    {
        $this->queue = [];
    }

    private function exists(int $sequenceID): bool
    {
        if ($this->expected > $sequenceID) {
            return true;
        }
        return isset($this->queue[$sequenceID]);
    }

    private function merge(): void
    {
        while (true) {
            if (!isset($this->queue[$this->expected])) {
                break;
            }
            unset($this->queue[$this->expected]);
            $this->expected++;
        }
    }
}
