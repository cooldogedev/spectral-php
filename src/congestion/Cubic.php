<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Protocol;
use function max;
use function min;
use function time;
use function pow;
use const INF;

final class Cubic
{
    private const MAX_CWND = 1000000;
    private const CUBIC_BETA = 0.7;
    private const CUBIC_C = 0.4;

    private float $cwnd;
    private float $wMax;
    private float $ssthres;
    private float $inFlight = 0;
    private float $k = 0;
    private int $epochStart = 0;

    public function __construct()
    {
        $this->cwnd = 10 * Protocol::MAX_PACKET_SIZE;
        $this->wMax = 10 * Protocol::MAX_PACKET_SIZE;
        $this->ssthres = INF;
    }

    public function onSend(int $bytes): bool
    {
        if ($this->cwnd - $this->inFlight >= $bytes) {
            $this->inFlight += $bytes;
            return true;
        }
        return false;
    }

    public function onAck(int $bytes): void
    {
        $this->inFlight = max($this->inFlight - $bytes, 0);
        if ($this->ssthres > $this->cwnd) {
            $this->cwnd = min($this->cwnd + $bytes, Cubic::MAX_CWND);
            return;
        }

        if ($this->epochStart === 0) {
            $this->epochStart = time();
            $this->k = pow($this->wMax * (1.0 - Cubic::CUBIC_BETA) / Cubic::CUBIC_C, 1/3);
        }

        $elapsed = time() - $this->epochStart;
        $cwnd = Cubic::CUBIC_C * pow($elapsed - $this->k, 3) + $this->wMax;
        if ($cwnd > 0) {
            $this->cwnd = min($this->cwnd, Cubic::MAX_CWND);
        }
    }

    public function onLoss(int $bytes): void
    {
        $this->inFlight = max($this->inFlight - $bytes, 0);
        $this->wMax = $this->cwnd;
        $this->cwnd *= Cubic::CUBIC_BETA;
        $this->ssthres = $this->cwnd;
        $this->epochStart = 0;
        $this->k = pow($this->wMax * (1.0 - Cubic::CUBIC_BETA) / Cubic::CUBIC_C, 1/3);
    }

    public function getCwnd(): float
    {
        return $this->cwnd;
    }

    public function getInFlight(): float
    {
        return $this->inFlight;
    }
}
