<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator;
use Wexample\SymfonyUser\Security\UserChecker;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class LoginFailureTest extends TestCase
{
    public function testTheAccountStatusIsToldOnlyWhenTheOptionIsOn(): void
    {
        $locked = new CustomUserMessageAccountStatusException(UserChecker::ERROR_ACCOUNT_LOCKED);

        $this->assertSame(['@form::error.invalid_credentials'], $this->errorsFor(false, $locked));
        $this->assertSame(['@form::' . UserChecker::ERROR_ACCOUNT_LOCKED], $this->errorsFor(true, $locked));
    }

    public function testAnUnknownAddressCostsAPasswordCheck(): void
    {
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willThrowException(new UserNotFoundException());

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturn('shield-hash');
        $hasher->expects($this->once())->method('verify')->with('shield-hash', 'typed password');

        $hasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);
        $hasherFactory->method('getPasswordHasher')->willReturn($hasher);

        $authenticator = new LoginFormAuthenticator(
            $this->createStub(UrlGeneratorInterface::class),
            $this->createProcessor(false),
            $this->createStub(FormResponsePayloadBuilder::class),
            $provider,
            $hasherFactory,
        );

        $request = new Request(request: ['login_form' => ['identifier' => 'nobody', 'password' => 'typed password']]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $this->expectException(UserNotFoundException::class);
        $authenticator->authenticate($request)->getUser();
    }

    public function testAnAccountWaitingForItsFirstPasswordCostsAPasswordCheck(): void
    {
        $provider = $this->createStub(UserProviderInterface::class);
        $provider->method('loadUserByIdentifier')->willReturn((new User())->setEmail('jane@example.com'));

        $hasher = $this->createMock(PasswordHasherInterface::class);
        $hasher->method('hash')->willReturn('shield-hash');
        $hasher->expects($this->once())->method('verify')->with('shield-hash', 'typed password');

        $hasherFactory = $this->createStub(PasswordHasherFactoryInterface::class);
        $hasherFactory->method('getPasswordHasher')->willReturn($hasher);

        $authenticator = new LoginFormAuthenticator(
            $this->createStub(UrlGeneratorInterface::class),
            $this->createProcessor(false),
            $this->createStub(FormResponsePayloadBuilder::class),
            $provider,
            $hasherFactory,
        );

        $request = new Request(request: ['login_form' => ['identifier' => 'jane@example.com', 'password' => 'typed password']]);
        $request->setSession(new Session(new MockArraySessionStorage()));

        $authenticator->authenticate($request)->getUser();
    }

    private function errorsFor(bool $reveal, \Throwable $exception): array
    {
        $errors = [];
        $form = $this->createStub(FormInterface::class);
        $form->method('addError')->willReturnCallback(function ($error) use (&$errors, $form) {
            $errors[] = $error->getMessage();

            return $form;
        });

        $this->createProcessor($reveal)->addAuthenticationError($form, $exception);

        return $errors;
    }

    private function createProcessor(bool $reveal): LoginFormProcessor
    {
        return new LoginFormProcessor(
            $this->createStub(FormFactoryInterface::class),
            new RequestStack(),
            $this->createStub(UrlGeneratorInterface::class),
            $reveal
        );
    }
}
