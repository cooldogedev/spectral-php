<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;
use function strlen;

final class ConnectionClose extends Frame
{
    public const CONNECTION_CLOSE_APPLICATION = 0;
    public const CONNECTION_CLOSE_GRACEFUL = 1;
    public const CONNECTION_CLOSE_TIMEOUT = 2;
    public const CONNECTION_CLOSE_INTERNAL = 3;

    public int $code;
    public string $message;

    public static function create(int $code, string $message): ConnectionClose
    {
        $fr = new ConnectionClose();
        $fr->code = $code;
        $fr->message = $message;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::CONNECTION_CLOSE;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeUnsignedByte($this->code);
        $buf->writeUnsignedIntLE(strlen($this->message));
        $buf->writeByteArray($this->message);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->code = $buf->readUnsignedByte();
        $this->message = $buf->readByteArray($buf->readUnsignedIntLE());
    }
}
