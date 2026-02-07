<?php

declare(strict_types=1);

$message = 'Hello from RoadRunner!';

$logDir = __DIR__ . '/../var/logs';
$logFile = $logDir . '/app.log';

$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);

return $message;
