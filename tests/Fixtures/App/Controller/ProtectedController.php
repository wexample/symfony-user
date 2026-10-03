<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App\Controller;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProtectedController
{
    /**
     * The home page, where a user without a role route lands.
     */
    #[Route(path: '/', name: 'home')]
    public function home(): Response
    {
        return new Response('home');
    }

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

    /**
     * Reserved to managers by `access_control`.
     */
    #[Route(path: '/manager', name: 'manager_home')]
    public function manager(): Response
    {
        return new Response('manager');
    }

    /**
     * Reserved by a voter: the organizations of the user only.
     */
    #[Route(path: '/organizations/{id}', name: 'organization')]
    #[IsGranted('ORGANIZATION_VIEW', subject: 'id')]
    public function organization(string $id): Response
    {
        return new Response('organization ' . $id);
    }
}
