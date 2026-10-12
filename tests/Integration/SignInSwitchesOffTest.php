<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;
use Wexample\SymfonyUser\Tests\Fixtures\App\NothingKeptAppKernel;

/**
 * `remember_me: false` and no trusted device: a switch nothing would honour
 * is not drawn.
 */
class SignInSwitchesOffTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return NothingKeptAppKernel::class;
    }

    public function testNeitherSwitchIsOffered(): void
    {
        $forms = self::getContainer()->get(FormFactoryInterface::class);

        $this->assertFalse($forms->create(LoginForm::class)->has(LoginForm::FIELD_REMEMBER_ME));
        $this->assertFalse($forms->create(TwoFactorCodeForm::class)->has(TwoFactorCodeForm::FIELD_TRUSTED));
    }
}
