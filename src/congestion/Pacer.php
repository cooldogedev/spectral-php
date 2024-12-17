<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\Math;
use cooldogedev\spectral\util\Time;
use function floor;
use function max;
use function min;

final class Pacer
{
    private const BURST_INTERVAL_NANOSECONDS = Time::MILLISECOND * 2;
    private const MIN_BURST_SIZE = 10;
    private const MAX_BURST_SIZE = 256;

    private int $capacity = 0;
    private int $tokens = 0;
    private int $mss = 0;
    private int $window = 0;
    private int $prev;

    public function __construct()
    {
        $this->prev = Time::unixNano();
    }

    public function getTimeUntilSend(int $now, int $rtt, int $bytes, int $mss, int $window): int
    {
        if ($mss !== $this->mss || $window !== $this->window) {
            $this->capacity = Pacer::optimalCapacity($rtt, $mss, $window);
            $this->tokens = min($this->tokens, $this->capacity);
            $this->mss = $mss;
            $this->window = $window;
        }

        if ($this->tokens >= $bytes || $window >= Math::MAX_UINT32) {
            return 0;
        }

        $elapsed = $now - $this->prev;
        $elapsedRTT = Time::nanosecondsToSeconds($elapsed) / Time::nanosecondsToSeconds(max($rtt, 1));
        $newTokens = $window * 1.25 * $elapsedRTT;
        $this->tokens = min($this->tokens + (int)floor($newTokens), $this->capacity);
        $this->prev = $now;
        if ($this->tokens >= $bytes) {
            return 0;
        }
        $unscaledDelay = $rtt * (min($bytes, $this->capacity) - $this->tokens) / $window;
        return $this->prev + (int)floor(($unscaledDelay / 5) * 4);
    }

    public function onSend(int $bytes): void
    {
        if ($this->tokens > $bytes) {
            $this->tokens -= $bytes;
        } else {
            $this->tokens = 0;
        }
    }

    private static function optimalCapacity(int $rtt, int $mss, int $window): int
    {
        $rttNs = max($rtt, 1);
        $capacity = (int)floor(($window * Pacer::BURST_INTERVAL_NANOSECONDS) / $rttNs);
        return Math::clamp($capacity, Pacer::MIN_BURST_SIZE * $mss, Pacer::MAX_BURST_SIZE * $mss);
    }
}
