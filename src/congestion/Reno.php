<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\log\Logger;
use cooldogedev\spectral\util\Math;
use function floor;
use function max;

final class Reno extends Controller
{
    private const LOSS_REDUCTION_FACTOR = 0.5;

    private int $window;
    private int $ssthres = Math::MAX_INT63;
    private int $bytesAcked = 0;

    public function __construct(Logger $logger, int $mss)
    {
        parent::__construct($logger, $mss);
        $this->window = $this->initialWindow();
    }

    public function onAck(int $now, int $sent, int $recoveryTime, RTT $rtt, int $bytes, int $flight): void
    {
        if (!$this->shouldIncreaseWindow($flight, $this->ssthres, $this->window)) {
            return;
        }

        if ($this->window < $this->ssthres) {
            $this->window += $this->mss;
            $this->logger->log("congestion_window_increase", "cause", "slow_start", "window", $this->window, "ssthres", $this->ssthres);
            if ($this->window >= $this->ssthres) {
                $this->ssthres = $this->window;
                $this->logger->log("congestion_exist_slow_start", "window", $this->window, "ssthres", $this->ssthres);
            }
        } else {
            $this->bytesAcked += $bytes;
            if ($this->bytesAcked >= $this->window) {
                $this->bytesAcked -= $this->window;
                $this->window += $this->mss;
                $this->logger->log("congestion_window_increase", "cause", "congestion_avoidance", "window", $this->window, "ssthres", $this->ssthres, "acked", $this->bytesAcked);
            }
        }
    }

    public function onCongestionEvent(int $now, int $sent): void
    {
        $this->window = (int)floor($this->window * Reno::LOSS_REDUCTION_FACTOR);
        $this->window = max($this->window, $this->minimumWindow());
        $this->bytesAcked = (int)floor($this->window * Reno::LOSS_REDUCTION_FACTOR);
        $this->ssthres = $this->window;
        $this->logger->log("congestion_window_decrease", "window", $this->window, "ssthres", $this->ssthres);
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
}
