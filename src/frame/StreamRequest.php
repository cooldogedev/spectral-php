<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;

final class StreamRequest extends Frame
{
    public int $streamID;

    public static function create(int $streamID): StreamRequest
    {
        $fr = new StreamRequest();
        $fr->streamID = $streamID;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::STREAM_REQUEST;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->streamID);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->streamID = $buf->readSignedLongLE();
    }
}
