<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\log\Logger;
use cooldogedev\spectral\util\Math;

abstract class Controller
{
    public function __construct(protected readonly Logger $logger, protected int $mss) {}

    final public function getMSS(): int
    {
        return $this->mss;
    }

    final public function setMSS(int $mss): void
    {
        $this->mss = $mss;
    }

    final protected function initialWindow(): int
    {
        return Math::clamp(14720, 2 * $this->mss, 10 * $this->mss);
    }

    final protected function minimumWindow(): int
    {
        return 2 * $this->mss;
    }

    abstract public function onAck(int $now, int $sent, int $recoveryTime, int $rtt, int $bytes): void;
    abstract public function onCongestionEvent(int $now, int $sent): void;
    abstract public function getWindow(): int;
}
