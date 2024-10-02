<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function count;

final class RetransmissionQueue
{
    private const RETRANSMISSION_DELAY = 5000;

    /**
     * @var array<int, RetransmissionEntry>
     */
    private array $queue = [];

    public function add(int $sequenceID, string $data): void
    {
        $this->queue[$sequenceID] = new RetransmissionEntry($data, Utils::unixNano());
    }

    public function nack(int $sequenceID): void
    {
        if (isset($this->queue[$sequenceID])) {
            $this->queue[$sequenceID]->nack = true;
        }
    }

    public function remove(int $sequenceID): ?RetransmissionEntry
    {
        $entry = $this->queue[$sequenceID] ?? null;
        if ($entry !== null) {
            unset($this->queue[$sequenceID]);
            return $entry;
        }
        return null;
    }

    public function shift(): ?string
    {
        if (count($this->queue) === 0) {
            return null;
        }

        $now = Utils::unixMicro();
        foreach ($this->queue as $entry) {
            if ($entry->nack || $now - $entry->timestamp >= RetransmissionQueue::RETRANSMISSION_DELAY) {
                $entry->nack = false;
                $entry->timestamp = $now;
                return $entry->payload;
            }
        }
        return null;
    }
}
