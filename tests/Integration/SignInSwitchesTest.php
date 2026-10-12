<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;

/**
 * The default fixture keeps both: a remember-me firewall, trusted devices on.
 * "Stay signed in" and "trust this device" are offered.
 */
class SignInSwitchesTest extends KernelTestCase
{
    public function testBothSwitchesAreOffered(): void
    {
        $forms = self::getContainer()->get(FormFactoryInterface::class);

        $this->assertTrue($forms->create(LoginForm::class)->has(LoginForm::FIELD_REMEMBER_ME));
        $this->assertTrue($forms->create(TwoFactorCodeForm::class)->has(TwoFactorCodeForm::FIELD_TRUSTED));
    }
}
