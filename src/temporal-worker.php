<?php
declare(strict_types=1);

use Temporal\WorkerFactory;

ini_set('display_errors', 'stderr');
require __DIR__ . '/../vendor/autoload.php';

$factory = WorkerFactory::create();
$worker = $factory->newWorker();

$worker->registerWorkflowTypes(\App\Workflow\SayHelloWorkflow::class);
$worker->registerActivity(\App\Activity\GreetingActivity::class);

$factory->run();
