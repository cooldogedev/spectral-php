<?php

declare(strict_types=1);

namespace cooldogedev\spectral\congestion;

use cooldogedev\spectral\util\log\Logger;

final class Sender
{
    private readonly Controller $cc;
    private readonly Pacer $pacer;

    private int $flight = 0;
    private bool $recoverySend = false;
    private int $recoveryStartTime;

    public function __construct(Logger $logger, int $now, int $mss)
    {
        $this->cc = new Reno($logger, $mss);
        $this->pacer = new Pacer();
        $this->recoveryStartTime = $now;
    }

    public function getTimeUntilSend(int $now, RTT $rtt, int $bytes): int
    {
        return $this->pacer->getTimeUntilSend($now, $rtt->getSRTT(), $bytes, $this->cc->getMSS(), $this->cc->getWindow());
    }

    public function onSend(int $bytes): void
    {
        $this->flight += $bytes;
        $this->pacer->onSend($bytes);
        if ($this->recoverySend) {
            $this->recoverySend = false;
        }
    }

    public function onAck(int $now, int $sent, RTT $rtt, int $bytes): void
    {
        if ($this->flight > $bytes) {
            $this->flight -= $bytes;
        } else {
            $this->flight = 0;
        }
        $this->cc->onAck($now, $sent, $this->recoveryStartTime, $rtt, $bytes, $this->flight);
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

    public function getAvailable(): int
    {
        if ($this->recoverySend) {
            return $this->cc->getMSS();
        }

        $window = $this->cc->getWindow();
        if ($window > $this->flight) {
            return $window - $this->flight;
        }
        return 0;
    }
}
