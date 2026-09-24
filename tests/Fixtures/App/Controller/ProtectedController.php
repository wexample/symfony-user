<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProtectedController
{
    #[Route(path: '/protected', name: 'protected')]
    public function index(): Response
    {
        return new Response('protected');
    }
}
