<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Protocol;
use function floor;
use function max;
use function min;
use function pow;
use function time;

final class Cubic
{
    private const MAX_BURST_PACKETS = 3;

    private const WINDOW_INITIAL = Protocol::MAX_PACKET_SIZE * 32;
    private const WINDOW_MIN = Protocol::MAX_PACKET_SIZE * 2;
    private const WINDOW_MAX = Protocol::MAX_PACKET_SIZE * 10000;

    private const CUBIC_BETA = 0.7;
    private const CUBIC_C = 0.4;

    private int $cwnd = Cubic::WINDOW_INITIAL;
    private int $wMax = Cubic::WINDOW_INITIAL;
    private int $ssthres = Protocol::MAX_BYTE_COUNT;
    private int $inFlight = 0;
    private int $epochStart = 0;
    private float $k = 0.0;

    public function canSend(int $bytes): bool
    {
        return $this->cwnd - $this->inFlight >= $bytes;
    }

    public function onSend(int $bytes): void
    {
        $this->inFlight += $bytes;
    }

    public function onAck(int $bytes): void
    {
        $this->inFlight = max($this->inFlight - $bytes, 0);
        if (!$this->shouldIncreaseWindow()) {
            return;
        }

        if ($this->ssthres > $this->cwnd) {
            $this->cwnd = min($this->cwnd + $bytes, Cubic::WINDOW_MAX);
            return;
        }

        if ($this->epochStart === 0) {
            $this->epochStart = time();
            $this->k = pow($this->wMax * (1.0 - Cubic::CUBIC_BETA) / Cubic::CUBIC_C, 1/3);
        }

        $elapsed = time() - $this->epochStart;
        $cwnd = (int)floor(Cubic::CUBIC_C * pow($elapsed - $this->k, 3) + $this->wMax);
        if ($cwnd > $this->cwnd) {
            $this->cwnd = min($cwnd, Cubic::WINDOW_MAX);
        }
    }

    public function onLoss(): void
    {
        $this->inFlight = 0;
        $this->wMax = $this->cwnd;
        $this->cwnd = (int)floor(max($this->cwnd*Cubic::CUBIC_BETA, Cubic::WINDOW_MIN));
        $this->ssthres = $this->cwnd;
        $this->epochStart = 0;
        $this->k = pow($this->wMax * (1.0 - Cubic::CUBIC_BETA) / Cubic::CUBIC_C, 1/3);
    }

    public function getCwnd(): int
    {
        return $this->cwnd;
    }

    private function shouldIncreaseWindow(): bool
    {
        if ($this->inFlight >= $this->cwnd) {
            return true;
        }
        $availableBytes = $this->cwnd - $this->inFlight;
        $slowStartLimited = $this->ssthres > $this->cwnd && $this->inFlight > $this->cwnd/2;
        return $slowStartLimited || $availableBytes <= Cubic::MAX_BURST_PACKETS*Protocol::MAX_PACKET_SIZE;
    }
}
