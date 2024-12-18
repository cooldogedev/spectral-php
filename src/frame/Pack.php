<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use cooldogedev\spectral\Protocol;
use pmmp\encoding\ByteBuffer;

final class Pack
{
    private static ?ByteBuffer $buf = null;

    public static function packSingle(Frame $fr): string
    {
        $buf = Pack::getBuffer();
        $buf->writeUnsignedIntLE($fr->id());
        $fr->encode($buf);
        return $buf->toString();
    }

    public static function pack(int $connectionID, int $sequenceID, string $frames): string
    {
        $buf = Pack::getBuffer();
        $buf->writeByteArray(Protocol::MAGIC);
        $buf->writeSignedLongLE($connectionID);
        $buf->writeUnsignedIntLE($sequenceID);
        $buf->writeByteArray($frames);
        return $buf->toString();
    }

    public static function unpack(string $payload): ?array
    {
        $buf = Pack::getBuffer();
        if (strlen($payload) < Protocol::PACKET_HEADER_SIZE) {
            return null;
        }

        $buf->writeByteArray($payload);
        if ($buf->readByteArray(4) !== Protocol::MAGIC) {
            return null;
        }

        $connectionID = $buf->readSignedLongLE();
        $sequenceID = $buf->readUnsignedIntLE();
        $frames = [];
        while ($buf->getUsedLength() > $buf->getReadOffset()) {
            $fr = Pool::getFrame($buf->readUnsignedIntLE());
            if ($fr === null) {
                break;
            }
            $fr->decode($buf);
            $frames[] = $fr;
        }
        return [$connectionID, $sequenceID, $frames];
    }

    private static function getBuffer(): ByteBuffer
    {
        if (Pack::$buf === null) {
            Pack::$buf = new ByteBuffer();
        }
        Pack::$buf->setReadOffset(0);
        Pack::$buf->setWriteOffset(0);
        Pack::$buf->clear();
        return Pack::$buf;
    }
}
