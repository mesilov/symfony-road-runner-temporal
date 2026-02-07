<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HelloController
{
    #[Route('/', name: 'hello')]
    public function index(): Response
    {
        return new Response(
            sprintf('Hello from Symfony + RoadRunner! %s', time()),
            Response::HTTP_OK,
            ['Content-Type' => 'text/plain']
        );
    }
}
