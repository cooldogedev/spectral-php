<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\log\Logger;
use function max;

final class Sender
{
    private readonly Controller $cc;

    private int $flight = 0;

    private bool $recoverySend = false;
    private int $recoveryStartTime;

    public function __construct(Logger $logger, int $now, int $mss)
    {
        $this->cc = new Reno($logger, $mss);
        $this->recoveryStartTime = $now;
    }

    public function onSend(int $bytes): void
    {
        $this->flight += $bytes;
        if ($this->recoverySend) {
            $this->recoverySend = false;
        }
    }

    public function onAck(int $now, int $sent, int $rtt, int $bytes): void
    {
        $this->flight = max($this->flight - $bytes, 0);
        $this->cc->onAck($now, $sent, $this->recoveryStartTime, $rtt, $bytes);
    }

    public function onCongestionEvent(int $now, int $sent): void
    {
        if ($sent > $this->recoveryStartTime) {
            $this->recoverySend = true;
            $this->recoveryStartTime = $now;
            $this->cc->onCongestionEvent($now, $sent);
        }
    }

    public function setMSS(int $mss): void
    {
        $this->cc->setMSS($mss);
    }

    public function getWindow(): int
    {
        return $this->cc->getWindow();
    }

    public function getAvailable(): int
    {
        if ($this->recoverySend) {
            return $this->cc->getMSS();
        }
        return $this->cc->getWindow() - $this->flight;
    }
}
