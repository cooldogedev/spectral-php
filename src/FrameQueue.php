<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

final class FrameQueue
{
    private int $expected = 0;

    /**
     * @var array<int, string>
     */
    private array $queue =  [];

    public function enqueue(string $payload, int $sequenceID): void
    {
        $this->queue[$sequenceID] = $payload;
    }

    public function dequeue(): ?string
    {
        $payload = $this->queue[$this->expected] ?? null;
        if ($payload === null) {
            return null;
        }
        unset($this->queue[$this->expected]);
        $this->expected++;
        return $payload;
    }

    public function clear(): void
    {
        $this->queue = [];
    }
}
