<?php

declare(strict_types=1);

namespace cooldogedev\example;

use cooldogedev\spectral\Listener;
use cooldogedev\spectral\ServerConnection;
use cooldogedev\spectral\Stream;
use Exception;

require dirname(__DIR__) . "/vendor/autoload.php";

$address = "0.0.0.0";
$port = 8080;

try {
    $listener = Listener::listen($address, $port);
} catch (Exception $exception) {
    echo "failed to listen on :8080 due to: " . $exception->getMessage() . PHP_EOL;
    return;
}

$streamAcceptor = static function (Stream $stream): void {
    $stream->registerReader(static function (string $data) use ($stream): void {
        echo "received: " . $data . PHP_EOL;
        $stream->write($data);
        echo "sent: " . $data . PHP_EOL;
    });
};
$listener->setConnectionAcceptor(static fn (ServerConnection $connection) => $connection->setStreamAcceptor($streamAcceptor));
echo "started listening on :8080" . PHP_EOL;
while ($listener->tick());
