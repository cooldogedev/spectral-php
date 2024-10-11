<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\frame\Frame;
use function floor;
use function microtime;

final class Utils
{
    public static function unixNano(): int
    {
        return (int) floor(microtime(true) * 1_000_000_000);
    }

    public static function unixMicro(): int
    {
        return (int) floor(microtime(true) * 1_000_000);
    }

    public static function unixMilli(): int
    {
        return (int) floor(microtime(true) * 1_000);
    }

    /**
     * @param array<int, Frame> $frames
     */
    public static function hasFrame(int $frameID, array $frames): bool
    {
        foreach ($frames as $fr) {
            if ($fr->id() === $frameID) {
                return true;
            }
        }
        return false;
    }

    public static function clamp(int $value, int $min, int $max): int
    {
        if ($value < $min) {
            return $min;
        } else if ($value > $max) {
            return $max;
        }
        return $value;
    }
}
