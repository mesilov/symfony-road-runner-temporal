<?php

declare(strict_types=1);

$message = sprintf('Hello from RoadRunner! %s', time());

$logDir = __DIR__ . '/../var/logs';
$logFile = $logDir . '/app.log';

$timestamp = date('Y-m-d H:i:s');
file_put_contents($logFile, "[$timestamp] $message" . PHP_EOL, FILE_APPEND);

return $message;
