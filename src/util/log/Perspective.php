<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util\log;

final class Perspective
{
    public const PERSPECTIVE_CLIENT = 0;
    public const PERSPECTIVE_SERVER = 1;

    public static function toString(int $perspective): string
    {
        return match ($perspective) {
            Perspective::PERSPECTIVE_CLIENT => "client",
            Perspective::PERSPECTIVE_SERVER => "server",
            default => "unknown",
        };
    }
}
