<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Protocol;
use cooldogedev\spectral\util\Time;
use function floor;
use function max;
use function min;

final class RTT
{
    private const INITIAL_RTT = Time::MILLISECOND * 333;

    private bool $measured = false;
    private int $minRTT = RTT::INITIAL_RTT;
    private int $latestRTT = 0;
    private int $smoothedRTT = RTT::INITIAL_RTT;
    private int $rttVar = RTT::INITIAL_RTT / 2;

    public function add(int $latestRTT, int $ackDelay): void
    {
        if ($latestRTT <= 0) {
            return;
        }

        $this->latestRTT = $latestRTT;
        if (!$this->measured) {
            $this->measured = true;
            $this->minRTT = $latestRTT;
            $this->smoothedRTT = $latestRTT;
            $this->rttVar = (int)floor($latestRTT / 2);
            return;
        }

        $this->minRTT = min($this->minRTT, $latestRTT);
        $ackDelay = min($ackDelay, Protocol::MAX_ACK_DELAY);
        $adjustedRTT = $latestRTT;
        if ($latestRTT >= $this->minRTT + $ackDelay) {
            $adjustedRTT -= $ackDelay;
        }

        if ($this->smoothedRTT > $adjustedRTT) {
            $rttSample = $this->smoothedRTT - $adjustedRTT;
        } else {
            $rttSample = $adjustedRTT - $this->smoothedRTT;
        }
        $this->rttVar = (int)floor(((3 * $this->rttVar) + $rttSample) / 4);
        $this->smoothedRTT = (int)floor(((7 * $this->smoothedRTT) + $adjustedRTT) / 8);
    }

    public function getLatestRTT(): int
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
