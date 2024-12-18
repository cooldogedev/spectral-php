<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\util\Time;

interface Protocol
{
    public const MAGIC = "\x20\x24\x10\x01";

    public const SEND_BUFFER_SIZE = 1024 * 1024 * 7;

    public const RECEIVE_BUFFER_SIZE = 1024 * 1024 * 7;

    public const PACKET_HEADER_SIZE = 16;

    public const MIN_PACKET_SIZE = 1200;

    public const MAX_PACKET_SIZE = 1452;

    public const MAX_ACK_DELAY = Time::MILLISECOND * 25;

    public const MAX_ACK_RANGES = 128;

    public const TIMER_GRANULARITY = Time::MILLISECOND * 2;
}
