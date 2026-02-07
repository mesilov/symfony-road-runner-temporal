<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Worker;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;

$worker = Worker::create();
$factory = new Psr17Factory();
$psr7 = new PSR7Worker($worker, $factory, $factory, $factory);

while (true) {
    $request = $psr7->waitRequest();
    if ($request === null) {
        break;
    }

    try {
        $body = require __DIR__ . '/test.php';

        $response = new Response(200, ['Content-Type' => 'text/plain'], $body);
        $psr7->respond($response);
    } catch (\Throwable $e) {
        $psr7->getWorker()->error($e->getMessage());
    }
}
