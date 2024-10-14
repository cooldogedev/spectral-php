<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util;

use cooldogedev\spectral\Protocol;
use RuntimeException;
use Socket;
use function socket_setopt;
use const IPPROTO_IP;
use const IP_MTU_DISCOVER;
use const IP_PMTUDISC_DO;
use const SO_RCVBUF;
use const SO_SNDBUF;
use const SOL_SOCKET;

final class UDP
{
    private const WINDOWS_IP_DF = 14;

    public static function optimizeSocket(Socket $socket): void
    {
        if (!socket_setopt($socket, SOL_SOCKET, SO_RCVBUF, Protocol::RECEIVE_BUFFER_SIZE)) {
            throw new RuntimeException("failed to set socket receive buffer");
        }

        if (!socket_setopt($socket, SOL_SOCKET, SO_SNDBUF, Protocol::SEND_BUFFER_SIZE)) {
            throw new RuntimeException("failed to set socket send buffer");
        }

        [$level, $option, $value] = match (OS::getOS()) {
            OS::OS_LINUX => [IPPROTO_IP, IP_MTU_DISCOVER, IP_PMTUDISC_DO],
            OS::OS_WINDOWS => [IPPROTO_IP, UDP::WINDOWS_IP_DF, 1],
            default => [null, null, null]
        };
        if ($level !== null && !socket_setopt($socket, $level, $option, $value)) {
            throw new RuntimeException("failed to set DF flag");
        }
    }
}
