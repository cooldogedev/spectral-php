<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\util\Address;
use Socket;
use function socket_close;
use function socket_sendto;
use function strlen;

final readonly class Conn
{
    public function __construct(
        public Socket $socket,
        public Address $localAddress,
        public Address $remoteAddress,
        public bool $closable,
    ) {}

    public function write(string $data): bool
    {
        $written = @socket_sendto($this->socket, $data, strlen($data), 0, $this->remoteAddress->address, $this->remoteAddress->port);
        return $written !== false && $written > 0;
    }

    public function close(): void
    {
        if ($this->closable) {
            @socket_close($this->socket);
        }
    }
}
