<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

use function floor;
use function microtime;

final class Time
{
    public const SECOND = 1_000_000_000;
    public const MILLISECOND = 1_000_000;
    public const MICROSECOND = 1000;
    public const NANOSECOND = 1;

    public static function unixNano(): int
    {
        return (int)floor(microtime(true) * 1_000_000_000);
    }

    public static function unixMicro(): int
    {
        return (int)floor(microtime(true) * 1_000_000);
    }

    public static function unixMilli(): int
    {
        return (int)floor(microtime(true) * 1_000);
    }

    public static function nanosecondsToSeconds(int $nanoseconds): float
    {
        $sec = $nanoseconds / Time::SECOND;
        $nsec = $nanoseconds % Time::SECOND;
        return (float)$sec + (float)$nsec / 1e9;
    }
}
