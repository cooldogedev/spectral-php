<?php

declare(strict_types=1);

namespace cooldogedev\example;

use cooldogedev\spectral\Dial;
use cooldogedev\spectral\Stream;
use Exception;

require dirname(__DIR__) . "/vendor/autoload.php";

$address = "127.0.0.1";
$port = 8080;
$message = "Hello, World!";

try {
    $connection = Dial::dial($address, $port);
} catch (Exception $exception) {
    echo "failed to dial :8080 due to: " . $exception->getMessage() . PHP_EOL;
    return;
}

$connection->openStream(static function (?Stream $stream) use ($message): void {
    if ($stream === null) {
        echo "failed to open stream" . PHP_EOL;
        return;
    }
    $stream->registerReader(static function (string $data): void {
        echo "received: " . $data . PHP_EOL;
    });
    $stream->write($message);
    echo "sent: " . $message . PHP_EOL;
});

while ($connection->tick());
