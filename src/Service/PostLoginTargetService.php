<?php

namespace Wexample\SymfonyUser\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\AccessDecisionManagerInterface;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Http\AccessMapInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

/**
 * Where a user lands once signed in — second factor passed, gates cleared:
 *
 * - the page they were sent away from, when `access_control` lets them in;
 * - otherwise the route of the first role of `post_login.routes` they hold,
 *   through the hierarchy;
 * - otherwise `post_login.default_route`, or the home page.
 *
 * Only `access_control` is read: a page refused by its controller still
 * answers 403.
 */
class PostLoginTargetService
{
    use TargetPathTrait;

    /**
     * @param array<string, string> $routes role => route, the first held wins
     */
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly AccessDecisionManagerInterface $accessDecisionManager,
        #[Autowire(service: 'security.access_map')]
        private readonly AccessMapInterface $accessMap,
        #[Autowire(param: 'wexample_symfony_user.post_login.routes')]
        private readonly array $routes = [],
        #[Autowire(param: 'wexample_symfony_user.post_login.default_route')]
        private readonly ?string $defaultRoute = null,
    ) {
    }

    /**
     * Takes the saved target path out of the session: it serves once.
     */
    public function getTargetUrl(Request $request, TokenInterface $token, string $firewallName): string
    {
        $session = $request->getSession();
        $saved = $this->getTargetPath($session, $firewallName);
        $this->removeTargetPath($session, $firewallName);

        if ($saved && $this->isReachable($saved, $request, $token)) {
            return $saved;
        }

        return $this->getRoleUrl($request, $token);
    }

    public function getRoleUrl(Request $request, TokenInterface $token): string
    {
        $held = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());

        foreach ($this->routes as $role => $route) {
            if (in_array($role, $held, true)) {
                return $this->urlGenerator->generate($route);
            }
        }

        return $this->defaultRoute
            ? $this->urlGenerator->generate($this->defaultRoute)
            : $request->getBasePath() . '/';
    }

    private function isReachable(string $url, Request $request, TokenInterface $token): bool
    {
        // A target path is saved from the request itself: an absolute URL of
        // this host, or a path.
        $target = Request::create($url, server: $request->server->all());

        if ($target->getHost() !== $request->getHost()) {
            return false;
        }

        [$attributes] = $this->accessMap->getPatterns($target);

        return ! $attributes || $this->accessDecisionManager->decide($token, $attributes, $target, null, true);
    }
}
