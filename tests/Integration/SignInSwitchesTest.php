<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;

/**
 * The fixture asks for both switches (`remember_me: true`, `trust_device:
 * ask`), on a remember-me firewall with trusted devices on: both offered —
 * "stay signed in" only where the firewall remembers.
 */
class SignInSwitchesTest extends KernelTestCase
{
    public function testBothSwitchesAreOffered(): void
    {
        $forms = self::getContainer()->get(FormFactoryInterface::class);

        // Outside a request, no firewall is known to remember: no switch.
        $this->assertFalse($forms->create(LoginForm::class)->has(LoginForm::FIELD_REMEMBER_ME));

        self::getContainer()->get('request_stack')->push(Request::create('/login'));

        $this->assertTrue($forms->create(LoginForm::class)->has(LoginForm::FIELD_REMEMBER_ME));
        $this->assertNotInstanceOf(
            HiddenType::class,
            $forms->create(TwoFactorCodeForm::class)->get(TwoFactorCodeForm::FIELD_TRUSTED)->getConfig()->getType()->getInnerType()
        );
    }
}
