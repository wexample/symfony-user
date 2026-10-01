<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorage;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyUser\EventSubscriber\RememberLocaleSubscriber;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class RememberLocaleSubscriberTest extends TestCase
{
    public function testTheLanguageTheUrlNamesIsKeptOnTheAccount(): void
    {
        $user = new User();

        $this->dispatch($user, ['_locale' => 'en'], flushes: 1);

        $this->assertSame('en', $user->getLocale());
    }

    public function testNothingIsWrittenWhileTheLanguageStaysTheSame(): void
    {
        $user = (new User())->setLocale('en');

        $this->dispatch($user, ['_locale' => 'en'], flushes: 0);

        $this->assertSame('en', $user->getLocale());
    }

    public function testAUrlWithoutALanguageLeavesTheAccountsOwn(): void
    {
        $user = (new User())->setLocale('en');

        $this->dispatch($user, [], flushes: 0);

        $this->assertSame('en', $user->getLocale());
    }

    public function testALanguageTheApplicationDoesNotSpeakIsIgnored(): void
    {
        $user = new User();

        $this->dispatch($user, ['_locale' => 'zh'], flushes: 0);

        $this->assertNull($user->getLocale());
    }

    public function testNothingIsKeptUnlessTheApplicationAsksForIt(): void
    {
        $user = new User();

        $this->dispatch($user, ['_locale' => 'en'], flushes: 0, enabled: false);

        $this->assertNull($user->getLocale());
    }

    /**
     * @param array<string, string> $attributes the request's, as the router set them
     */
    private function dispatch(
        User $user,
        array $attributes,
        int $flushes,
        bool $enabled = true
    ): void {
        $tokenStorage = new TokenStorage();
        $tokenStorage->setToken(new UsernamePasswordToken($user, 'main', $user->getRoles()));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly($flushes))->method('flush');

        $subscriber = new RememberLocaleSubscriber(
            $tokenStorage,
            $entityManager,
            new LocaleService('fr', ['fr', 'en'], true, []),
            $enabled,
        );

        $subscriber->onKernelRequest(new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(attributes: $attributes),
            HttpKernelInterface::MAIN_REQUEST
        ));
    }
}
