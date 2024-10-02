<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

use pmmp\encoding\ByteBuffer;
use function array_slice;
use function count;

final class Acknowledgement extends Frame
{
    public const ACKNOWLEDGEMENT_WITH_GAPS = 0;
    public const ACKNOWLEDGEMENT_WITHOUT_GAPS = 1;

    public int $type;
    public int $delay;
    /**
     * @var array<0, array{0: int, 1: int}>
     */
    public array $ranges;

    public static function create(int $type, int $delay, array $ranges): Acknowledgement
    {
        $fr = new Acknowledgement();
        $fr->type = $type;
        $fr->delay = $delay;
        $fr->ranges = $ranges;
        return $fr;
    }

    public function id(): int
    {
        return FrameIds::ACKNOWLEDGEMENT;
    }

    public function encode(ByteBuffer $buf): void
    {
        $buf->writeUnsignedByte($this->type);
        $buf->writeSignedLongLE($this->delay);
        $buf->writeUnsignedIntLE(count($this->ranges));
        foreach ($this->ranges as $range) {
            $buf->writeUnsignedIntLE($range[0]);
            $buf->writeUnsignedIntLE($range[1]);
        }
    }

    public function decode(ByteBuffer $buf): void
    {
        $this->type = $buf->readUnsignedByte();
        $this->delay = $buf->readSignedLongLE();
        $length = $buf->readUnsignedIntLE();
        for ($i = 0; $i < $length; $i++) {
            $this->ranges[$i] = [
                $buf->readUnsignedIntLE(),
                $buf->readUnsignedIntLE(),
            ];
        }
    }

    /**
     * @return array{0: int, 1: array<int, array{0: int, 1: int}>}
     */
    public static function generateAcknowledgementRange(array $list): array
    {
        $type = Acknowledgement::ACKNOWLEDGEMENT_WITHOUT_GAPS;
        $ranges = [];
        $start = $list[0];
        $end = $list[0];
        foreach (array_slice($list, 1) as $value) {
            if ($value !== $end + 1) {
                $type = Acknowledgement::ACKNOWLEDGEMENT_WITH_GAPS;
                $ranges[] = [$start, $end];
                $start = $value;
            }
            $end = $value;
        }
        $ranges[] = [$start, $end];
        return [$type, $ranges];
    }

    /**
     * @param array<int, array{0: int, 1: int}> $ranges
     * @return array<int>
     */
    public static function generateAcknowledgementGaps(array $ranges): array
    {
        $gaps = [];
        for ($i = 0; $i < count($ranges) - 1; $i++) {
            $currentEnd = $ranges[$i][1];
            $nextStart = $ranges[$i + 1][0];
            if ($nextStart > $currentEnd + 1) {
                for ($j = $currentEnd + 1; $j < $nextStart; $j++) {
                    $gaps[] = $j;
                }
            }
        }
        return $gaps;
    }
}
