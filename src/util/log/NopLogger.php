<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util\log;

final class NopLogger extends Logger
{
    public function setConnectionID(int $connectionID): void {}

    public function log(string $event, mixed ...$params): void {}

    public function close(): void {}
}
