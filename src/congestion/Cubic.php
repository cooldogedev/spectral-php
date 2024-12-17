<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\log\Logger;
use cooldogedev\spectral\util\Math;
use cooldogedev\spectral\util\Time;
use function floor;
use function max;
use function pow;

final class Cubic extends Controller
{
    private const CUBIC_BETA = 0.7;
    private const CUBIC_C = 0.4;

    private int $window;
    private float $wMax;
    private int $ssthres = Math::MAX_INT63;
    private int $cwndInc = 0;
    private float $k = 0;

    public function __construct(Logger $logger, int $mss)
    {
        parent::__construct($logger, $mss);
        $this->window = $this->initialWindow();
        $this->wMax = (float)$this->initialWindow();
    }

    public function onAck(int $now, int $sent, int $recoveryTime, RTT $rtt, int $bytes, int $flight): void
    {
        if ($this->window < $this->ssthres) {
            $this->window += $bytes;
            $this->logger->log("congestion_window_increase", "cause", "slow_start", "window", $this->window);
            return;
        }

        $t = $now - $recoveryTime;
        $w = Cubic::wCubic($t + $rtt->getSRTT(), $this->wMax, $this->k, $this->mss);
        $est = Cubic::wEst($t, $rtt->getSRTT(), $this->wMax, $this->mss);
        $cubicCwnd = $this->window;
        if ($w < $est) {
            $cubicCwnd = max($cubicCwnd, (int)floor($est));
        } else if ($cubicCwnd < (int)floor($w)) {
            $cubicCwnd += (int)floor(($w - $cubicCwnd) / $cubicCwnd * $this->mss);
        }

        $this->cwndInc += $cubicCwnd - $this->window;
        if ($this->cwndInc >= $this->mss) {
            $this->window += $this->mss;
            $this->cwndInc = 0;
            $this->logger->log("congestion_window_increase", "cause", "congestion_avoidance", "window", $this->window);
        }
    }

    public function onCongestionEvent(int $now, int $sent): void
    {
        if ($this->window < $this->wMax) {
            $this->wMax = $this->window * (1.0 - Cubic::CUBIC_BETA) / 2.0;
        } else {
            $this->wMax = $this->window;
        }
        $this->ssthres = max((int)floor($this->wMax * Cubic::CUBIC_BETA), $this->minimumWindow());
        $this->window = $this->ssthres;
        $this->k = Cubic::cubicK($this->wMax, $this->mss);
        $this->cwndInc = (int)floor($this->cwndInc * Cubic::CUBIC_BETA);
        $this->logger->log("congestion_window_decrease", "window", $this->window);
    }

    public function setMSS(int $mss): void
    {
        $this->mss = $mss;
        $this->window = max($this->window, $this->minimumWindow());
    }

    public function getWindow(): int
    {
        return $this->window;
    }

    private static function cubicK(float $wMax, int $mss): float
    {
        return pow($wMax / $mss * (1.0 - Cubic::CUBIC_BETA) / Cubic::CUBIC_C, 1 / 3);
    }

    private static function wCubic(int $t, float $wMax, float $k, int $mss): float
    {
        return Cubic::CUBIC_C * (pow(Time::nanosecondsToSeconds($t)-$k, 3) + $wMax/$mss) * $mss;
    }

    private static function wEst(int $t, int $rtt, float $wMax, int $mss): float
    {
        return ($wMax / $mss * Cubic::CUBIC_BETA + 3.0 * (1.0 - Cubic::CUBIC_BETA) / (1.0 + Cubic::CUBIC_BETA) * Time::nanosecondsToSeconds($t) / Time::nanosecondsToSeconds($rtt)) * $mss;
    }
}
