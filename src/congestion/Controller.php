<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\Protocol;
use cooldogedev\spectral\util\log\Logger;
use cooldogedev\spectral\util\Math;

abstract class Controller
{
    public function __construct(protected readonly Logger $logger, protected int $mss) {}

    final public function initialWindow(): int
    {
        return Math::clamp(14720, 2 * $this->mss, 10 * $this->mss);
    }

    final public function minimumWindow(): int
    {
        return 2 * $this->mss;
    }

    final public function getMSS(): int
    {
        return $this->mss;
    }

    final protected function shouldIncreaseWindow(int $flight, int $ssthresh, int $window): bool
    {
        if ($flight >= $window) {
            return true;
        }
        $available = $window - $flight;
        $slowStartLimited = $ssthresh > $window && $flight > $window / 2;
        return $slowStartLimited || $available <= 3 * Protocol::MAX_PACKET_SIZE;
    }

    abstract public function onAck(int $now, int $sent, int $recoveryTime, RTT $rtt, int $bytes, int $flight): void;
    abstract public function onCongestionEvent(int $now, int $sent): void;
    abstract public function setMSS(int $mss): void;
    abstract public function getWindow(): int;
}
