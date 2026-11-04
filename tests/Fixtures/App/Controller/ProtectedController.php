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
     * Reached by a script and not by a reader, which the leading underscore
     * of the path is the convention for: it does not keep a session alive.
     */
    #[Route(path: '/_program', name: 'program')]
    public function program(): Response
    {
        return new Response('program');
    }

    /**
     * Under the fixture's stateless `api` firewall.
     */
    #[Route(path: '/api/protected', name: 'api_protected')]
    #[IsGranted('ROLE_USER')]
    public function apiProtected(): Response
    {
        return new Response('api protected');
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
