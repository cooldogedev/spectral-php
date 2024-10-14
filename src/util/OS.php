<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

use function file_exists;
use function php_uname;
use function str_starts_with;
use function stripos;

final class OS
{
    public const OS_WINDOWS = "win";
    public const OS_IOS = "ios";
    public const OS_MACOS = "mac";
    public const OS_ANDROID = "android";
    public const OS_LINUX = "linux";
    public const OS_BSD = "bsd";
    public const OS_UNKNOWN = "other";

    private static ?string $os = null;

    public static function getOS() : string
    {
        if (OS::$os !== null) {
            return OS::$os;
        }

        $uname = php_uname("s");
        if (stripos($uname, "Darwin") !== false) {
            if (str_starts_with(php_uname("m"), "iP")) {
                OS::$os = OS::OS_IOS;
            } else {
                OS::$os = OS::OS_MACOS;
            }
        } elseif (stripos($uname, "Win") !== false || $uname === "Msys") {
            OS::$os = OS::OS_WINDOWS;
        } elseif (stripos($uname, "Linux") !== false) {
            if (@file_exists("/system/build.prop")) {
                OS::$os = OS::OS_ANDROID;
            } else {
                OS::$os = OS::OS_LINUX;
            }
        } elseif (stripos($uname, "BSD") !== false || $uname === "DragonFly") {
            OS::$os = OS::OS_BSD;
        } else {
            OS::$os = OS::OS_UNKNOWN;
        }
        return OS::$os;
    }
}
