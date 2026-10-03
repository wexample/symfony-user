<?php

namespace Wexample\SymfonyUser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Wexample\SymfonyUser\EventSubscriber\ImpersonationGuardSubscriber;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\ImpersonationService;

/**
 * The accounts the signed-in user may impersonate matching `q`, for a
 * searchable select. Absent where the firewall has no `switch_user`; refused
 * to whoever lacks its role; limited in results and in rate.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
final class ImpersonationSearchController extends AbstractController
{
    #[Route(path: '/account/impersonate/search', name: UserRoute::IMPERSONATE_SEARCH, methods: [Request::METHOD_GET])]
    public function search(Request $request, ImpersonationService $impersonation): JsonResponse
    {
        if (! $impersonation->getSwitchUserConfig()) {
            throw $this->createNotFoundException();
        }

        if (! $impersonation->canImpersonate()) {
            $exception = $this->createAccessDeniedException();
            $exception->setAttributes(ImpersonationGuardSubscriber::ATTRIBUTE);

            throw $exception;
        }

        return new JsonResponse([
            'targets' => array_map(
                $impersonation->describe(...),
                $impersonation->searchTargets($impersonation->getActor(), (string) $request->query->get('q'))
            ),
        ]);
    }
}
