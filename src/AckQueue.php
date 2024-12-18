<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\util\Time;
use function array_slice;
use function array_splice;
use function count;
use function floor;
use function max;
use function uasort;

final class AckQueue
{
    private int $max = 0;
    private int $maxTime = 0;
    private int $nextAck = 0;

    /**
     * @var array<0, array{0: int, 1: int}>
     */
    public array $ranges = [];

    public function add(int $now, int $sequenceID): void
    {
        foreach ($this->ranges as $i => [$start, $end]) {
            if ($sequenceID >= $start && $sequenceID <= $end) {
                return;
            }

            if ($sequenceID === $end + 1) {
                $this->ranges[$i][1] = $sequenceID;
                $this->merge();
                return;
            }

            if ($sequenceID + 1 === $start) {
                $this->ranges[$i][0] = $sequenceID;
                $this->merge();
                return;
            }
        }

        $this->ranges[] = [$sequenceID, $sequenceID];
        if ($sequenceID > $this->max) {
            $this->max = $sequenceID;
            $this->maxTime = $now;
        }

        if ($this->nextAck === 0) {
            $this->nextAck = $now + (Protocol::MAX_ACK_DELAY - Protocol::TIMER_GRANULARITY);
        }
    }

    /**
     * @return array{0: array<int>, 1: int, 2: int}|null
     */
    public function flush(int $now, int $length, bool $append): ?array
    {
        $length = min(count($this->ranges), $length);
        if ($length > 0 && ($now >= $this->nextAck || $append)) {
            $ranges = array_splice($this->ranges, 0, $length);
            $max = $this->max;
            $delay = (int)floor(($now - $this->maxTime) / Time::MICROSECOND);
            if ($delay < 0) {
                $delay = 0;
            }

            if (count($this->ranges) === 0) {
                $this->max = 0;
                $this->maxTime = 0;
                $this->nextAck = 0;
            }
            return [$ranges, $max, $delay];
        }
        return null;
    }

    public function clear(): void
    {
        $this->ranges = [];
    }

    private function merge(): void
    {
        if (count($this->ranges) <= 1) {
            return;
        }

        uasort($this->ranges, static fn (array $a, array $b) => $a[0] <=> $b[0]);
        $merged = [];
        $current = $this->ranges[0];
        foreach (array_slice($this->ranges, 1) as $next) {
            [$start, $end] = $current;
            [$nextStart, $nextEnd] = $next;
            if ($nextStart <= $end+1){
                $current[1] = max($end, $nextEnd);
            } else {
                $merged = [... $merged, $current];
                $current = $next;
            }
        }
        $this->ranges = [... $merged, $current];
    }
}
