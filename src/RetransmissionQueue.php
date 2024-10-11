<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function count;

final class RetransmissionQueue
{
    private const RETRANSMISSION_ATTEMPTS = 3;
    private const RETRANSMISSION_DELAY = 3_000_000_000;

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

        $now = Utils::unixNano();
        foreach ($this->queue as $sequenceID => $entry) {
            if ($entry->nack || $now - $entry->timestamp >= RetransmissionQueue::RETRANSMISSION_DELAY) {
                $entry->timestamp = $now;
                $entry->nack = false;
                $entry->attempts++;
                if ($entry->attempts >= RetransmissionQueue::RETRANSMISSION_ATTEMPTS) {
                    unset($this->queue[$sequenceID]);
                }
                return $entry->payload;
            }
        }
        return null;
    }
}
