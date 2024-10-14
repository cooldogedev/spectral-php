<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\util\Time;
use function min;

final class MTUDiscovery
{
    public const MTU_MIN = 1200;
    public const MTU_MAX = 1452;
    public const MTU_DIFF = 20;

    private const PROBE_DELAY = 5;
    private const PROBE_ATTEMPTS = 3;

    public int $current = MTUDiscovery::MTU_MIN;
    public int $flight = 0;
    public int $prev;

    public bool $discovered = false;

    public function __construct(public ?Closure $mtuIncrease)
    {
        $this->prev = Time::unixNano();
        $this->discover();
    }

    public function onAck(int $mtu): void
    {
        if ($this->current !== $mtu) {
            return;
        }

        ($this->mtuIncrease)($this->current);
        if (!$this->discovered) {
            $this->discover();
        }
    }

    public function sendProbe(int $now, int $rtt): bool
    {
        if ($now - $this->prev < $rtt * MTUDiscovery::PROBE_DELAY) {
            return false;
        }

        if ($this->flight >= MTUDiscovery::PROBE_ATTEMPTS) {
            $this->discovered = false;
            return false;
        }
        $this->flight++;
        $this->prev = $now;
        return true;
    }

    private function discover(): void
    {
        if ($this->current >= MTUDiscovery::MTU_MAX) {
            $this->discovered = true;
            return;
        }
        $this->flight = 0;
        $this->current = min($this->current + MTUDiscovery::MTU_DIFF, MTUDiscovery::MTU_MAX);
    }
}
