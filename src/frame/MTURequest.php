<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;
use function str_repeat;

final class MTURequest extends Frame
{
    public int $mtu;

    public static function create(int $mtu): MTURequest
    {
        $fr = new MTURequest();
        $fr->mtu = $mtu;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::MTU_REQUEST;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->mtu);
        $buf->writeByteArray(str_repeat("\x00", $this->mtu - 8));
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->mtu = $buf->readSignedLongLE();
        $buf->readByteArray($this->mtu - 8);
    }
}
