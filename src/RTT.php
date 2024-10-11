<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function floor;

final class RTT
{
    private const RTT_ALPHA = 0.125;
    private const RTT_ONE_MINUS_ALPHA = 1.0 - RTT::RTT_ALPHA;

    private int $rtt = 0;

    public function add(int $rtt): void
    {
        if ($rtt <= 0) {
            return;
        }

        if ($this->rtt === 0) {
            $this->rtt = $rtt;
        } else {
            $this->rtt = (int)floor(RTT::RTT_ALPHA * $rtt + RTT::RTT_ONE_MINUS_ALPHA * $this->rtt);
        }
    }

    public function get(): int
    {
        return $this->rtt;
    }
}
