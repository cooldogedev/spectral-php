<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\ConnectionClose;
use cooldogedev\spectral\frame\Frame;
use cooldogedev\spectral\frame\FrameIds;
use cooldogedev\spectral\frame\Pack;
use cooldogedev\spectral\util\Address;
use cooldogedev\spectral\util\OS;
use cooldogedev\spectral\util\UDP;
use RuntimeException;
use Socket;
use function socket_bind;
use function socket_create;
use function socket_getsockname;
use function socket_recvfrom;
use function socket_select;
use function spl_object_id;
use const AF_INET;
use const MSG_DONTWAIT;
use const SOCK_DGRAM;
use const SOL_UDP;

final class Listener
{
    /**
     * @var array<int, ServerConnection>
     */
    private array $connections = [];
    private int $connectionId = 0;

    /**
     * @var null|Closure(ServerConnection $stream): void
     */
    private ?Closure $connectionAcceptor = null;

    /**
     * @var array<int, Socket>
     */
    private array $sockets = [];
    /**
     * @var array<int, Closure>
     */
    private array $socketHandlers = [];

    private Address $localAddress;

    private bool $closed = false;

    private function __construct(private readonly string $address, private readonly int $port)
    {
        $this->registerSockets();
    }

    private function registerSockets(): void
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        UDP::optimizeSocket($socket);
        if (!@socket_bind($socket, $this->address, $this->port)) {
            @socket_close($socket);
            throw new RuntimeException("failed to bind socket");
        }
        socket_getsockname($socket, $localAddress, $localPort);
        $this->localAddress = new Address($localAddress, $localPort);
        $this->registerSocket($socket, fn () => $this->readSocket($socket));
    }

    public static function listen(string $address, int $port = 0): Listener
    {
        return new Listener($address, $port);
    }

    /**
     * @param null|Closure(ServerConnection $stream): void $connectionAcceptor
     */
    public function setConnectionAcceptor(?Closure $connectionAcceptor): void
    {
        $this->connectionAcceptor = $connectionAcceptor;
    }

    public function registerSocket(Socket $socket, Closure $handler): void
    {
        $socketId = spl_object_id($socket);
        $this->sockets[$socketId] = $socket;
        $this->socketHandlers[$socketId] = $handler;
    }

    public function tick(): bool
    {
        if ($this->closed) {
            return false;
        }
        $this->selectSockets(50);
        $this->tickConnections();
        return true;
    }

    public function close(): void
    {
        if (!$this->closed) {
            foreach ($this->connections as $id => $connection) {
                $connection->closeWithError(ConnectionClose::CONNECTION_CLOSE_GRACEFUL, "listener closed");
                unset($this->connections[$id]);
            }

            foreach ($this->sockets as $socketId => $socket) {
                @socket_close($socket);
                unset($this->sockets[$socketId]);
            }
            $this->closed = true;
        }
    }

    private function selectSockets(int $timeout): void
    {
        $read = $this->sockets;
        $write = null;
        $except = null;
        $changed = @socket_select($read, $write, $except, 0, $timeout);
        if ($changed !== false && $changed > 0) {
            foreach ($read as $id => $socket) {
                $this->socketHandlers[$id]();
            }
        }
    }

    private function tickConnections(): void
    {
        foreach ($this->connections as $connectionID => $connection) {
            if (!$connection->tick()) {
                unset($this->connections[$connectionID]);
            }
        }
    }

    public function readSocket(Socket $socket): void
    {
        while (($length = @socket_recvfrom($socket, $buffer, 1500, OS::getOS() !== OS::OS_WINDOWS ? MSG_DONTWAIT : 0, $address, $port)) !== false) {
            $packet = Pack::unpack($buffer);
            if ($packet === null) {
                return;
            }

            [$connectionID, $sequenceID, $frames] = $packet;
            $connection = $this->connections[$connectionID] ?? null;
            if ($connection === null && Listener::hasFrame(FrameIds::CONNECTION_REQUEST, $frames)) {
                $connection = new ServerConnection(new Conn($socket, $this->localAddress, new Address($address, $port), false), $this->connectionId);
                $connection->logger->log("connection_accepted", "address", $address, "port", $port);
                $this->connections[$this->connectionId] = $connection;
                $this->connectionId++;
                if ($this->connectionAcceptor !== null) {
                    ($this->connectionAcceptor)($connection);
                }
            }

            $connection?->receive($sequenceID, $frames);
            if ($this->closed) {
                return;
            }
        }
    }

    /**
     * @param array<int, Frame> $frames
     */
    private static function hasFrame(int $frameID, array $frames): bool
    {
        foreach ($frames as $fr) {
            if ($fr->id() === $frameID) {
                return true;
            }
        }
        return false;
    }
}
