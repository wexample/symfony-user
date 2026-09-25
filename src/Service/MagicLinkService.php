<?php

namespace Wexample\SymfonyUser\Service;

use LogicException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\LoginLink\LoginLinkDetails;
use Symfony\Component\Security\Http\LoginLink\LoginLinkHandlerInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Interface\MagicLinkSenderInterface;

/**
 * Creates the links of the `login_link` key of the firewall. Their signature
 * holds the password hash and the last login date: a password change, or any
 * login, turns every link sent before into a dead one.
 */
class MagicLinkService
{
    public const string TARGET_PATH_PARAMETER = '_target_path';

    public function __construct(
        private readonly MagicLinkSenderInterface $sender,
        private readonly RequestStack $requestStack,
        // Both absent when no firewall declares `login_link`. The first one
        // picks the firewall of the current request; the second one serves
        // the links created without a request (a command, a worker).
        private readonly ?LoginLinkHandlerInterface $requestLoginLinkHandler = null,
        #[Autowire(service: 'security.authenticator.login_link_handler.main')]
        private readonly ?LoginLinkHandlerInterface $mainLoginLinkHandler = null,
    ) {
    }

    /**
     * A link for an application mail (an invitation, a renewal reminder),
     * landing on $targetPath once signed in.
     */
    public function createLink(
        AbstractUser $user,
        ?string $targetPath = null,
        ?int $lifetime = null
    ): LoginLinkDetails {
        $handler = $this->requestStack->getCurrentRequest()
            ? $this->requestLoginLinkHandler
            : $this->mainLoginLinkHandler;

        if (! $handler) {
            throw new LogicException('Declare the login_link key on the firewall to create magic links.');
        }

        $link = $handler->createLoginLink($user, null, $lifetime);

        if ($targetPath === null) {
            return $link;
        }

        if (! str_starts_with($targetPath, '/') || str_starts_with($targetPath, '//')) {
            throw new LogicException(sprintf('The target path must be a local path, "%s" given.', $targetPath));
        }

        return new LoginLinkDetails(
            $link->getUrl() . '&' . http_build_query([self::TARGET_PATH_PARAMETER => $targetPath]),
            $link->getExpiresAt()
        );
    }

    /**
     * Only an account allowed to sign in gets a link.
     */
    public function sendLink(AbstractUser $user, ?string $targetPath = null): bool
    {
        if (! $user->isEnabled() || $user->isLocked()) {
            return false;
        }

        $this->sender->send($user, $this->createLink($user, $targetPath));

        return true;
    }
}
