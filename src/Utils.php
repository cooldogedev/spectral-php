<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\frame\Frame;
use function file_exists;
use function floor;
use function microtime;
use function php_uname;
use function str_starts_with;
use function stripos;

final class Utils
{
    public const OS_WINDOWS = "win";
    public const OS_IOS = "ios";
    public const OS_MACOS = "mac";
    public const OS_ANDROID = "android";
    public const OS_LINUX = "linux";
    public const OS_BSD = "bsd";
    public const OS_UNKNOWN = "other";

    private static ?string $os = null;

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

    public static function getOS() : string
    {
        if (Utils::$os !== null) {
            return Utils::$os;
        }

        $uname = php_uname("s");
        if (stripos($uname, "Darwin") !== false) {
            if (str_starts_with(php_uname("m"), "iP")) {
                Utils::$os = Utils::OS_IOS;
            } else {
                Utils::$os = Utils::OS_MACOS;
            }
        } elseif (stripos($uname, "Win") !== false || $uname === "Msys") {
            Utils::$os = Utils::OS_WINDOWS;
        } elseif (stripos($uname, "Linux") !== false) {
            if (@file_exists("/system/build.prop")) {
                Utils::$os = Utils::OS_ANDROID;
            } else {
                Utils::$os = Utils::OS_LINUX;
            }
        } elseif (stripos($uname, "BSD") !== false || $uname === "DragonFly") {
            Utils::$os = Utils::OS_BSD;
        } else {
            Utils::$os = Utils::OS_UNKNOWN;
        }
        return Utils::$os;
    }
}
