<?php
declare(strict_types=1);

namespace App\Workflow;

use App\Activity\GreetingActivity;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class SayHelloWorkflow
{
    #[WorkflowMethod]
    public function sayHello(string $name)
    {
        $activity = Workflow::newActivityStub(
            GreetingActivity::class,
            ActivityOptions::new()
                ->withStartToCloseTimeout(5),
        );

        return yield $activity->greet($name);
    }
}
