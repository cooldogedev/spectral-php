<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

use cooldogedev\spectral\Protocol;
use function abs;
use function floor;
use function max;
use function min;

final class RTT
{
    private const INITIAL_RTT = Time::MILLISECOND * 333;

    private int $minRTT = -1;
    private int $latestRTT = 0;
    private int $smoothedRTT = RTT::INITIAL_RTT;
    private int $rttVar = RTT::INITIAL_RTT / 2;

    public function add(int $latestRTT, int $ackDelay): void
    {
        $this->latestRTT = $latestRTT;
        if ($latestRTT < 0) {
            $this->minRTT = $latestRTT;
            $this->smoothedRTT = $latestRTT;
            $this->rttVar = (int)floor($latestRTT / 2);
            return;
        }

        $this->minRTT = min($this->minRTT, $latestRTT);
        $adjustedRTT = $latestRTT - min($ackDelay, Protocol::MAX_ACK_DELAY);
        if ($adjustedRTT < $this->minRTT) {
            $adjustedRTT = $this->latestRTT;
        }
        $this->smoothedRTT = (int)floor(((7 * $this->smoothedRTT) + $adjustedRTT) / 8);
        $this->rttVar = (int)floor(((3 * $this->rttVar) + abs($this->smoothedRTT - $adjustedRTT)) / 4);
    }

    public function getRTT(): int
    {
        return $this->latestRTT;
    }

    public function getSRTT(): int
    {
        return $this->smoothedRTT;
    }

    public function getRTTVAR(): int
    {
        return $this->rttVar;
    }

    public function getRTO(): int
    {
        return (int)floor($this->smoothedRTT + max(4 * $this->rttVar, Protocol::TIMER_GRANULARITY)) + Protocol::MAX_ACK_DELAY;
    }
}
