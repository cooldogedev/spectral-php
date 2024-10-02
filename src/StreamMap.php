<?php

declare(strict_types=1);

namespace cooldogedev\spectral;

final class StreamMap
{
    public array $streams = [];

    public function add(Stream $stream, int $streamID): void
    {
        $this->streams[$streamID] = $stream;
    }

    public function get(int $streamID): ?Stream
    {
        return $this->streams[$streamID] ?? null;
    }

    public function remove(int $streamID): void
    {
        unset($this->streams[$streamID]);
    }

    /**
     * @return array<int, Stream>
     */
    public function all(): array
    {
        return $this->streams;
    }
}
