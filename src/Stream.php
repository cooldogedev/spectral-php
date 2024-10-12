<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use Closure;
use cooldogedev\spectral\frame\StreamClose;
use cooldogedev\spectral\frame\StreamData;
use function count;
use function floor;
use function implode;
use function min;
use function strlen;
use function substr;

final class Stream
{
    public const MAX_PAYLOAD_SIZE = 128;

    public int $sequenceID = 0;
    public int $expectedSequenceID = 0;

    /**
     * @var array<int, string>
     */
    public array $ordered = [];
    /**
     * @var array<int, array<int, string>>
     */
    public array $splits = [];
    /**
     * @var array<int, int>
     */
    public array $splitsRemaining = [];

    /**
     * @var array<int, Closure(string $payload): void>
     */
    public array $readers = [];
    /**
     * @var array<int, Closure(): void>
     */
    public array $closeHandlers = [];

    public bool $closed = false;

    public function __construct(
        private readonly Connection $connection,
        private readonly StreamMap $map,
        private readonly int $streamID,
    ) {}

    /**
     * @param Closure(string $payload): void $reader
     */
    public function registerReader(Closure $reader): void
    {
        $this->readers[] = $reader;
    }

    public function registerCloseHandler(Closure $handler): void
    {
        $this->closeHandlers[] = $handler;
    }

    public function write(string $payload): void
    {
        $sequenceID = $this->sequenceID;
        $this->sequenceID++;
        if (strlen($payload) <= Stream::MAX_PAYLOAD_SIZE) {
            $this->connection->write(StreamData::create($this->streamID, $sequenceID, 0, 0, $payload));
            return;
        }

        $length = strlen($payload);
        $total = (int)floor(($length + Stream::MAX_PAYLOAD_SIZE - 1) / Stream::MAX_PAYLOAD_SIZE);
        for ($i = 0; $i < $total; $i++) {
            $start = $i * Stream::MAX_PAYLOAD_SIZE;
            $end = min($start + Stream::MAX_PAYLOAD_SIZE, $length);
            $this->connection->write(StreamData::create(
                streamID: $this->streamID,
                sequenceID: $sequenceID,
                total: $total,
                offset: $i,
                payload: substr($payload, $start, $end - $start),
            ));
        }
    }

    public function close(): void
    {
        $this->connection->write(StreamClose::create($this->streamID));
        $this->internalClose();
    }

    public function internalClose(): void
    {
        if (!$this->closed) {
            foreach ($this->closeHandlers as $closeHandler) {
                $closeHandler();
            }
            $this->readers = [];
            $this->closeHandlers = [];
            $this->closed = true;
            $this->map->remove($this->streamID);
        }
    }

    public function receive(StreamData $fr): void
    {
        if ($fr->total <= 0) {
            $this->handleFull($fr->sequenceID, $fr->payload);
            return;
        }

        if (!isset($this->splits[$fr->sequenceID])) {
            $this->splits[$fr->sequenceID] = array_fill(0, $fr->total, "");
            $this->splitsRemaining[$fr->sequenceID] = $fr->total;
        }

        $this->splits[$fr->sequenceID][$fr->offset] = $fr->payload;
        $this->splitsRemaining[$fr->sequenceID]--;
        if ($this->splitsRemaining[$fr->sequenceID] === 0) {
            $this->handleFull($fr->sequenceID, implode("", $this->splits[$fr->sequenceID]));
            unset(
                $this->splits[$fr->sequenceID],
                $this->splitsRemaining[$fr->sequenceID],
            );
        }
    }

    private function handleFull(int $sequenceID, string $payload): void
    {
        if ($sequenceID !== $this->expectedSequenceID) {
            $this->ordered[$sequenceID] = $payload;
            return;
        }

        $this->expectedSequenceID++;
        if (count($this->ordered) > 0) {
            $this->order();
        }
        $this->onRead($payload);
    }

    private function order(): void
    {
        while (true) {
            $nextPayload = $this->ordered[$this->expectedSequenceID] ?? null;
            if ($nextPayload === null) {
                break;
            }
            unset($this->ordered[$this->expectedSequenceID]);
            $this->expectedSequenceID++;
            $this->onRead($nextPayload);
        }
    }

    private function onRead(string $payload): void
    {
        foreach ($this->readers as $reader) {
            $reader($payload);
        }
    }
}
