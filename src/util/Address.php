<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

final readonly class Address
{
    public function __construct(public string $address, public int $port) {}

    public function toString(): string
    {
        return $this->address . ":" . $this->port;
    }
}
