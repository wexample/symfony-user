<?php

namespace Wexample\SymfonyUser\Interface;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * Something a signed-in user must do before reaching anything else: set an
 * authenticator app up, accept the terms. AccountGateSubscriber holds them on
 * the gate's page, the highest priority first; an application adds its own by
 * implementing this interface — the tag follows the class wherever it is.
 */
interface AccountGateInterface
{
    public const string TAG = 'wexample_user.account_gate';

    /**
     * Higher first.
     */
    public static function getPriority(): int;

    public function isBlocking(AbstractUser $user, TokenInterface $token): bool;

    /**
     * The requests the gate lets through while it blocks: its own page and
     * what that page posts to.
     */
    public function allowsRequest(Request $request): bool;

    /**
     * The route of the gate's page.
     */
    public function getRoute(): string;
}
