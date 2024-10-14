<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;

final class MTUResponse extends Frame
{
    public int $mtu;

    public static function create(int $mtu): MTUResponse
    {
        $fr = new MTUResponse();
        $fr->mtu = $mtu;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::MTU_RESPONSE;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->mtu);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->mtu = $buf->readSignedLongLE();
    }
}
