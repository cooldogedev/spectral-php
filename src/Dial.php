<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\frame\ConnectionClose;
use cooldogedev\spectral\frame\ConnectionRequest;
use cooldogedev\spectral\frame\ConnectionResponse;
use cooldogedev\spectral\util\Address;
use cooldogedev\spectral\util\UDP;
use RuntimeException;
use function socket_bind;
use function socket_create;
use function socket_getsockname;
use function time;
use const AF_INET;
use const SOCK_DGRAM;
use const SOL_UDP;

final class Dial
{
    public static function dial(string $address, int $port = 0, int $timeout = 30): ClientConnection
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        UDP::optimizeSocket($socket);
        if (!@socket_bind($socket, "0.0.0.0")) {
            throw new RuntimeException("failed to bind socket");
        }

        socket_getsockname($socket, $localAddress, $localPort);
        $connection = new ClientConnection(new Conn($socket, new Address($localAddress, $localPort), new Address($address, $port), true));
        $connection->logger->log("connection_request", "address", $address, "port", $port);
        $connection->writeControl(ConnectionRequest::create(), true);
        $start = time();
        while ($connection->connectionResponse === null) {
            if (time() - $start >= $timeout) {
                $connection->logger->log("connection_request_timeout");
                $connection->closeWithError(ConnectionClose::CONNECTION_CLOSE_TIMEOUT, "network inactivity");
                throw new RuntimeException("connection closed due to timeout");
            }

            if (!$connection->tick()) {
                $connection->logger->log("connection_request_tick_fail");
                throw new RuntimeException("could not establish connection");
            }
        }

        if ($connection->connectionResponse !== ConnectionResponse::CONNECTION_RESPONSE_SUCCESS) {
            $connection->logger->log("connection_request_fail");
            $connection->closeWithError(ConnectionClose::CONNECTION_CLOSE_INTERNAL, "failed to open connection");
            throw new RuntimeException("failed to bind socket");
        }
        $connection->logger->log("connection_request_success");
        return $connection;
    }
}
