<?php

declare(strict_types=1);

namespace cooldogedev\spectral\frame;

interface FrameIds
{
    public const ACKNOWLEDGEMENT = 0;

    public const CONNECTION_REQUEST = 1;
    public const CONNECTION_RESPONSE = 2;
    public const CONNECTION_CLOSE = 3;

    public const STREAM_REQUEST = 4;
    public const STREAM_RESPONSE = 5;
    public const STREAM_DATA = 6;
    public const STREAM_CLOSE = 7;

    public const MTU_REQUEST = 8;
    public const MTU_RESPONSE = 9;
}
