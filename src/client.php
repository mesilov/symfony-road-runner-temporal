<?php
declare(strict_types=1);

use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use App\Workflow\SayHelloWorkflow;

ini_set('display_errors', 'stderr');
require __DIR__ . '/../vendor/autoload.php';

$client = new WorkflowClient(
    ServiceClient::create('temporal:7233'),
);

$workflowStub = $client->newWorkflowStub(SayHelloWorkflow::class);
$result = $workflowStub->sayHello('Temporal');

echo "Result: {$result}\n";
