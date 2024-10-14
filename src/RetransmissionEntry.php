<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

final class RetransmissionEntry
{
    public function __construct(
        public string $payload,
        public int    $sent,
        public int    $attempts = 0,
    ) {}
}
