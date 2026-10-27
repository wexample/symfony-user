<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Form\TwoFactorCodeForm;
use Wexample\SymfonyUser\Tests\Fixtures\App\DefaultSignInAppKernel;

/**
 * The package's defaults: no switch on the sign-in forms, the device
 * trusted once its code is right.
 */
class SignInSwitchesDefaultTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return DefaultSignInAppKernel::class;
    }

    public function testNoSwitchIsDrawnAndTheDeviceIsTrusted(): void
    {
        self::getContainer()->get('request_stack')->push(Request::create('/login'));
        $forms = self::getContainer()->get(FormFactoryInterface::class);

        $this->assertFalse($forms->create(LoginForm::class)->has(LoginForm::FIELD_REMEMBER_ME));

        $trusted = $forms->create(TwoFactorCodeForm::class)->get(TwoFactorCodeForm::FIELD_TRUSTED);
        $this->assertInstanceOf(HiddenType::class, $trusted->getConfig()->getType()->getInnerType());
        $this->assertSame('1', $trusted->getData());
    }
}
