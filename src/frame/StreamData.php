<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;
use function strlen;

final class StreamData extends Frame
{
    public int $streamID;
    public int $sequenceID;
    public string $payload;

    public static function create(int $streamID, int $sequenceID, string $payload): StreamData
    {
        $fr = new StreamData();
        $fr->streamID = $streamID;
        $fr->sequenceID = $sequenceID;
        $fr->payload = $payload;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::STREAM_DATA;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->streamID);
        $buf->writeUnsignedIntLE($this->sequenceID);
        $buf->writeUnsignedIntLE(strlen($this->payload));
        $buf->writeByteArray($this->payload);
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->streamID = $buf->readSignedLongLE();
        $this->sequenceID = $buf->readUnsignedIntLE();
        $this->payload = $buf->readByteArray($buf->readUnsignedIntLE());
    }
}
