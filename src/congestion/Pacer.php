<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Protocol;
use cooldogedev\spectral\Utils;
use function floor;
use function max;
use function min;

final class Pacer
{
    private const SECOND = 1_000_000_000;
    private const MIN_PACING_DELAY = 2_000_000;
    private const MIN_BURST_SIZE = 2;
    private const MAX_BURST_SIZE = 10;

    private int $prev;
    private int $capacity = 0;
    private int $tokens = 0;
    private int $window = 0;
    private int $rtt = 0;
    private float $rate = 0.0;

    public function __construct()
    {
        $this->prev = Utils::unixNano();
    }

    public function delay(int $rtt, int $length, int $window): int
    {
        if ($window !== $this->window || $rtt !== $this->rtt) {
            $this->capacity = Pacer::optimalCapacity($rtt, $window);
            $this->tokens = min($this->capacity, $this->tokens);
            $this->window = $window;
            $this->rate = 1.25 * $window / max($rtt / Pacer::SECOND, 1);
            $this->rtt = $rtt;
        }

        if ($this->tokens >= $length) {
            return 0;
        }

        $now = Utils::unixNano();
        $newTokens = (int)floor($this->rate * (($now - $this->prev) / Pacer::SECOND));
        $this->tokens = min($this->tokens + $newTokens, $this->capacity);
        $this->prev = $now;
        if ($this->tokens >= $length) {
            return 0;
        }
        $delay = (int)floor(($length - $this->tokens) / ($this->rate * Pacer::SECOND));
        return max($delay, Pacer::MIN_PACING_DELAY);
    }

    public function onSend(int $length): void
    {
        $this->tokens = max($this->tokens - $length, 0);
    }

    private static function optimalCapacity(int $rtt, int $window): int
    {
        $rttNs = max($rtt, 1);
        $capacity = (int)floor(($window * Pacer::MIN_PACING_DELAY) / $rttNs);
        return Utils::clamp($capacity, Pacer::MIN_BURST_SIZE * Protocol::MAX_PACKET_SIZE, Pacer::MAX_BURST_SIZE * Protocol::MAX_PACKET_SIZE);
    }
}
