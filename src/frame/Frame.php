<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;

abstract class Frame
{
    abstract public function id(): int;

    abstract public function encode(ByteBuffer $buf): void;

    abstract public function decode(ByteBuffer $buf): void;
}
