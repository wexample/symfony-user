<?php

namespace Wexample\SymfonyUser\Security\Handler;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wexample\SymfonyUser\Controller\Pages\TwoFactorController;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Service\PostLoginTargetService;

/**
 * The `success_handler` of the firewall's `login_link`: a magic link lands
 * on the path it carries, or where PostLoginTargetService sends the user —
 * after the second factor, when one is asked.
 */
class LoginLinkSuccessHandler implements AuthenticationSuccessHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly PostLoginTargetService $postLoginTarget,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $firewallName = method_exists($token, 'getFirewallName') ? $token->getFirewallName() : 'main';
        $path = (string) $request->query->get(MagicLinkService::TARGET_PATH_PARAMETER);

        // MagicLinkService only writes local paths; a link edited by hand may not.
        if (str_starts_with($path, '/') && ! str_starts_with($path, '//')) {
            $this->saveTargetPath($request->getSession(), $firewallName, $path);
        }

        if ($token instanceof TwoFactorTokenInterface) {
            return new RedirectResponse($this->urlGenerator->generate(TwoFactorController::ROUTE_FORM));
        }

        return new RedirectResponse($this->postLoginTarget->getTargetUrl($request, $token, $firewallName));
    }
}
