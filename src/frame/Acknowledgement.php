<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;
use function count;

final class Acknowledgement extends Frame
{
    public int $delay;
    public int $max;
    /**
     * @var array<0, array{0: int, 1: int}>
     */
    public array $ranges;

    public static function create(int $delay, int $max, array $ranges): Acknowledgement
    {
        $fr = new Acknowledgement();
        $fr->delay = $delay;
        $fr->max = $max;
        $fr->ranges = $ranges;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::ACKNOWLEDGEMENT;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeSignedLongLE($this->delay);
        $buf->writeUnsignedIntLE($this->max);
        $buf->writeUnsignedIntLE(count($this->ranges));
        foreach ($this->ranges as $range) {
            $buf->writeUnsignedIntLE($range[0]);
            $buf->writeUnsignedIntLE($range[1]);
        }
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->delay = $buf->readSignedLongLE();
        $this->max = $buf->readUnsignedIntLE();
        $length = $buf->readUnsignedIntLE();
        for ($i = 0; $i < $length; $i++) {
            $this->ranges[$i] = [
                $buf->readUnsignedIntLE(),
                $buf->readUnsignedIntLE(),
            ];
        }
    }
}
