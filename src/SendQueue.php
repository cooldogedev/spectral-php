<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use cooldogedev\spectral\frame\Pack;
use function array_shift;
use function count;
use function strlen;

final class SendQueue
{
    private const PACKET_SIZE = Protocol::MAX_PACKET_SIZE - Protocol::PACKET_HEADER_SIZE;

    private int $sequenceID = 1;

    /**
     * @var array<int, string>
     */
    private array $queue = [];

    private string $packet = "";
    private int $total = 0;

    public function __construct(public int $connectionID) {}

    public function add(string $payload): void
    {
        $this->queue[] = $payload;
    }

    public function count(): int
    {
        return count($this->queue);
    }

    public function compute(): ?string
    {
        if ($this->total > 0) {
            return $this->packet;
        }

        if (count($this->queue) === 0) {
            return null;
        }

        while (count($this->queue) > 0) {
            if (strlen($this->packet) + strlen($this->queue[0]) > SendQueue::PACKET_SIZE) {
                break;
            }
            $this->total++;
            $this->packet .= array_shift($this->queue);
        }
        return $this->packet;
    }

    public function flush(): ?array
    {
        if ($this->total === 0) {
            return null;
        }
        $sequenceID = $this->sequenceID;
        $packet = Pack::pack($this->connectionID, $sequenceID, $this->total, $this->packet);
        $this->total = 0;
        $this->packet = "";
        $this->sequenceID++;
        return [$sequenceID, $packet];
    }
}
