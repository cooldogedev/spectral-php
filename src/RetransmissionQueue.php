<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

use function array_key_first;
use function count;
use function uasort;

final class RetransmissionQueue
{
    private const RETRANSMISSION_ATTEMPTS = 3;

    /**
     * @var array<int, RetransmissionEntry>
     */
    private array $queue = [];

    public function add(int $now, int $sequenceID, string $data): void
    {
        $this->queue[$sequenceID] = new RetransmissionEntry($data, $now);
        $this->sort();
    }

    public function remove(int $sequenceID): ?RetransmissionEntry
    {
        $entry = $this->queue[$sequenceID] ?? null;
        if ($entry !== null) {
            unset($this->queue[$sequenceID]);
            $this->sort();
            return $entry;
        }
        return null;
    }

    public function shift(int $now, int $rto): ?array
    {
        if (count($this->queue) === 0) {
            return null;
        }

        $sequenceID = array_key_first($this->queue);
        $entry = $this->queue[$sequenceID];
        if ($now - $entry->sent >= $rto) {
            $sent = $entry->sent;
            $entry->sent = $now;
            $entry->attempts++;
            if ($entry->attempts >= RetransmissionQueue::RETRANSMISSION_ATTEMPTS) {
                unset($this->queue[$sequenceID]);
            } else {
                $this->sort();
            }
            return [$sent, $entry->payload];
        }
        return null;
    }

    public function clear(): void
    {
        $this->queue = [];
    }

    private function sort(): void
    {
        uasort($this->queue, static fn(RetransmissionEntry $a, RetransmissionEntry $b): int => $a->sent <=> $b->sent);
    }
}
