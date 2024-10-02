<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;

final class StreamResponse extends Frame
{
    public const STREAM_RESPONSE_SUCCESS = 0;
    public const STREAM_RESPONSE_FAILED = 1;

    public int $streamID;
    public int $response;

    public static function create(int $streamID, int $response): StreamResponse
    {
        $fr = new StreamResponse();
        $fr->streamID = $streamID;
        $fr->response = $response;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::STREAM_RESPONSE;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->streamID);
        $buf->writeUnsignedByte($this->response);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->streamID = $buf->readSignedLongLE();
        $this->response = $buf->readUnsignedByte();
    }
}
