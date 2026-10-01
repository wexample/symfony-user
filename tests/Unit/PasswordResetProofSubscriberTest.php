<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Enum\PasswordResetMode;
use Wexample\SymfonyUser\EventSubscriber\PasswordResetProofSubscriber;
use Wexample\SymfonyUser\Service\PasswordResetService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

/**
 * In the magic link reset mode, a magic link waiting for its second factor
 * grants no reset proof until the code is checked.
 */
class PasswordResetProofSubscriberTest extends TestCase
{
    public function testTheProofWaitsForTheSecondFactor(): void
    {
        $user = (new User())->setEmail('jane@example.com');
        $session = new Session(new MockArraySessionStorage());

        $resetService = $this->createStub(PasswordResetService::class);
        $resetService->method('getMode')->willReturn(PasswordResetMode::MAGIC_LINK);
        $subscriber = new PasswordResetProofSubscriber($resetService);

        $granted = 0;
        $resetService->method('grantProof')->willReturnCallback(function () use (&$granted): void {
            ++$granted;
        });

        // The magic link, its second factor still to come: nothing yet.
        $subscriber->onLoginSuccess($this->event($user, $session, SecurityController::ROUTE_LOGIN_LINK, $this->createStub(TwoFactorTokenInterface::class)));
        $this->assertSame(0, $granted);

        // The code checked: now.
        $subscriber->onLoginSuccess($this->event($user, $session, 'form_processor_submit', $this->createStub(TokenInterface::class)));
        $this->assertSame(1, $granted);

        // A later sign-in by password grants nothing more.
        $subscriber->onLoginSuccess($this->event($user, $session, 'form_processor_submit', $this->createStub(TokenInterface::class)));
        $this->assertSame(1, $granted);
    }

    private function event(User $user, Session $session, string $route, TokenInterface $token): LoginSuccessEvent
    {
        $request = new Request(attributes: ['_route' => $route]);
        $request->setSession($session);

        return new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            new SelfValidatingPassport(new UserBadge('jane@example.com', static fn () => $user)),
            $token,
            $request,
            null,
            'main'
        );
    }
}
