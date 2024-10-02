<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\ConnectionClose;
use cooldogedev\spectral\frame\FrameIds;
use cooldogedev\spectral\frame\Pack;
use RuntimeException;
use Socket;
use function socket_bind;
use function socket_create;
use function socket_getsockname;
use function socket_recvfrom;
use function socket_read;
use function socket_select;
use function socket_setopt;
use const AF_INET;
use const SO_RCVBUF;
use const SO_SNDBUF;
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
    private array $socketInterruptions = [];

    private bool $closed = false;

    private function __construct(private readonly Socket $socket, private readonly Address $localAddress) {}

    public static function listen(string $address, int $port = 0): Listener
    {
        $socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
        if (!socket_setopt($socket, SOL_SOCKET, SO_RCVBUF, Protocol::RECEIVE_BUFFER_SIZE)) {
            @socket_close($socket);
            throw new RuntimeException("failed to set socket send buffer");
        }

        if (!socket_setopt($socket, SOL_SOCKET, SO_SNDBUF, Protocol::SEND_BUFFER_SIZE)) {
            @socket_close($socket);
            throw new RuntimeException("failed to set socket receive buffer");
        }

        if (!socket_bind($socket, $address, $port)) {
            @socket_close($socket);
            throw new RuntimeException("failed to bind socket");
        }
        socket_getsockname($socket, $localAddress, $localPort);
        return new Listener($socket, new Address($localAddress, $localPort));
    }

    /**
     * @param null|Closure(ServerConnection $stream): void $connectionAcceptor
     */
    public function setConnectionAcceptor(?Closure $connectionAcceptor): void
    {
        $this->connectionAcceptor = $connectionAcceptor;
    }

    public function registerSocketInterruption(Socket $socket): void
    {
        $this->socketInterruptions[] = $socket;
    }

    public function tick(): void
    {
        if ($this->closed) {
            return;
        }

        $this->read();
        foreach ($this->connections as $connectionID => $connection) {
            if (!$connection->tick()) {
                unset($this->connections[$connectionID]);
            }
        }
    }

    public function close(): void
    {
        if (!$this->closed) {
            foreach ($this->connections as $id => $connection) {
                $connection->closeWithError(ConnectionClose::CONNECTION_CLOSE_GRACEFUL, "listener closed");
                unset($this->connections[$id]);
            }
            @socket_close($this->socket);
            $this->closed = true;
        }
    }

    private function read(): void
    {
        $read = [$this->socket, ... $this->socketInterruptions];
        $write = null;
        $except = null;
        $changed = @socket_select($read, $write, $except, 0, 50);
        if ($changed === false) {
            return;
        }

        if ($changed === 0) {
            return;
        }

        $mainSocketChanged = false;
        foreach ($read as $socket) {
            if ($socket === $this->socket) {
                $mainSocketChanged = true;
            } else {
                @socket_read($socket, 65536);
            }
        }

        if (!$mainSocketChanged) {
            return;
        }

        $bytes = "";
        $address = "";
        $port = 0;
        $received = @socket_recvfrom($this->socket, $bytes, 1500, 0, $address, $port);
        if ($received === false) {
            return;
        }

        $packet = Pack::unpack($bytes);
        if ($packet === null) {
            return;
        }

        [$connectionID, $sequenceID, $frames] = $packet;
        $connection = $this->connections[$connectionID] ?? null;
        if ($connection === null && Utils::hasFrame(FrameIds::CONNECTION_REQUEST, $frames)) {
            $connection = new ServerConnection(new Conn($this->socket, $this->localAddress, new Address($address, $port), false), $this->connectionId);
            $this->connections[$this->connectionId] = $connection;
            $this->connectionId++;
            if ($this->connectionAcceptor !== null) {
                ($this->connectionAcceptor)($connection);
            }
        }
        $connection?->receive($sequenceID, $frames);
    }
}
