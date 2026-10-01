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

    #[Route(path: '/public', name: 'public')]
    public function public(): Response
    {
        return new Response('public');
    }

    /**
     * The text of the terms, owned by the application.
     */
    #[Route(path: '/terms-text', name: 'terms_text')]
    public function termsText(): Response
    {
        return new Response('terms text');
    }
}
