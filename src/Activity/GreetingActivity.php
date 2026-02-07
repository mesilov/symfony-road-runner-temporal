<?php
declare(strict_types=1);

namespace App\Activity;

use Temporal\Activity\ActivityInterface;
use Temporal\Activity\ActivityMethod;

#[ActivityInterface]
class GreetingActivity
{
    #[ActivityMethod]
    public function greet(string $name): string
    {
        return "Hello, $name!";
    }
}
