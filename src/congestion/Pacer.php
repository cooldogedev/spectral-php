<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Utils;
use function floor;
use function min;
use function round;

final class Pacer
{
    private const PACER_BYTES_PER_TOKEN = 512;

    private int $tokens;
    private int $lastRefill;

    public function __construct(private int $interval, private readonly int $capacity)
    {
        $this->tokens = $this->capacity;
        $this->lastRefill = Utils::unixMicro();
    }

    public function consume(int $bytes): bool
    {
        $now = Utils::unixMicro();
        $elapsed = $now - $this->lastRefill;
        if ($elapsed > 0) {
            $this->tokens = min($this->tokens + (int)floor(($elapsed/$this->interval)), $this->capacity);
            $this->lastRefill = $now;
        }

        $tokensNeeded = (int)round(($bytes + Pacer::PACER_BYTES_PER_TOKEN - 1) / Pacer::PACER_BYTES_PER_TOKEN);
        if ($this->tokens >= $tokensNeeded) {
            $this->tokens -= $tokensNeeded;
            return true;
        }
        return false;
    }

    public function setInterval(int $interval): void
    {
        if ($interval > 0) {
            $this->interval = $interval;
        }
    }
}
