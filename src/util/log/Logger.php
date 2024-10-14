<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util\log;

use function getenv;
use function is_string;
use function mkdir;

abstract class Logger
{
    abstract public function setConnectionID(int $connectionID): void;

    abstract public function log(string $event, mixed ... $params): void;

    abstract public function close(): void;

    public static function create(int $perspective): Logger
    {
        $path = getenv("SLOG_DIR");
        if (!is_string($path) || $path === "") {
            return new NopLogger();
        }
        @mkdir($path, 0755);
        return new FileLogger($path, $perspective);
    }
}
