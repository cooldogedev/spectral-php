<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;

final class ConnectionResponse extends Frame
{
    public const CONNECTION_RESPONSE_SUCCESS = 0;
    public const CONNECTION_RESPONSE_FAILED = 1;

    public int $connectionID;
    public int $response;

    public static function create(int $connectionID, int $response): ConnectionResponse
    {
        $fr = new ConnectionResponse();
        $fr->connectionID = $connectionID;
        $fr->response = $response;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::CONNECTION_RESPONSE;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->connectionID);
        $buf->writeUnsignedByte($this->response);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->connectionID = $buf->readSignedLongLE();
        $this->response = $buf->readUnsignedByte();
    }
}
