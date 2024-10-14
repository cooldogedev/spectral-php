<?php

declare(strict_types=1);

namespace cooldogedev\spectral\util\log;

use DateTime;
use DateTimeInterface;
use Symfony\Component\Filesystem\Path;
use const FILE_APPEND;
use function bin2hex;
use function count;
use function file_put_contents;
use function implode;
use function random_bytes;
use function trim;

final class FileLogger extends Logger
{
    private const BUFFER_MAX = 50;

    private array $buffered = [];
    private string $path = "";
    private int $connectionID = 0;

    public function __construct(private readonly string $dir, private readonly int $perspective) {}

    public function setConnectionID(int $connectionID): void
    {
        $this->connectionID = $connectionID;
        $bytes = random_bytes(20);
        $this->path = Path::join($this->dir, bin2hex($bytes) . "-" . $this->connectionID . "." . Perspective::toString($this->perspective) . ".log");
    }

    public function log(string $event, mixed ...$params): void
    {
        $pairs = [];
        for ($i = 0; $i < count($params); $i += 2) {
            $pairs[] = $params[$i] . "=" . $params[$i+1];
        }

        $date = new DateTime();
        $this->buffered[] = trim("timestamp=" . $date->format(DateTimeInterface::RFC3339) . " event=" . $event . " " . implode(" ", $pairs));
        if (count($this->buffered) >= FileLogger::BUFFER_MAX) {
            $this->flush();
        }
    }

    public function close(): void
    {
        $this->flush();
    }

    private function flush(): void
    {
        @file_put_contents($this->path, implode("\n", $this->buffered) . "\n", FILE_APPEND);
        $this->buffered = [];
    }
}
