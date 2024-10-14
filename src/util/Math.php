<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

final class Math
{
    public const MAX_INT63 = 0x7fffffffffffffff;
    public const MAX_UINT32 = 0xffffffff;

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
