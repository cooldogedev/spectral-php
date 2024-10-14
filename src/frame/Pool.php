<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

final class Pool
{
    public static function getFrame(int $id): ?Frame
    {
        return match ($id) {
            FrameIds::ACKNOWLEDGEMENT => new Acknowledgement(),

            FrameIds::CONNECTION_REQUEST => new ConnectionRequest(),
            FrameIds::CONNECTION_RESPONSE => new ConnectionResponse(),
            FrameIds::CONNECTION_CLOSE => new ConnectionClose(),

            FrameIds::STREAM_REQUEST => new StreamRequest(),
            FrameIds::STREAM_RESPONSE => new StreamResponse(),
            FrameIds::STREAM_DATA => new StreamData(),
            FrameIds::STREAM_CLOSE => new StreamClose(),

            FrameIds::MTU_REQUEST => new MTURequest(),
            FrameIds::MTU_RESPONSE => new MTUResponse(),
            default => null,
        };
    }
}
