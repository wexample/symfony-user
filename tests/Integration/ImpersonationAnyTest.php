<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Wexample\SymfonyUser\Tests\Fixtures\App\ImpersonationAnyAppKernel;

/**
 * `targets: any`, as in development: every account, above the actor
 * included — never oneself, a disabled one, or one an application guard
 * keeps out.
 */
class ImpersonationAnyTest extends AbstractImpersonationTestCase
{
    protected static function getKernelClass(): string
    {
        return ImpersonationAnyAppKernel::class;
    }

    public function testEveryActiveAccountIsATarget(): void
    {
        $this->assertSame(
            ['owner@example.com', 'support-a@example.com', 'support-b@example.com'],
            $this->identifiers($this->getService()->listTargets($this->find('manager')))
        );

        $this->login('manager');
        $this->assertTrue($this->chooseAccount('owner@example.com')['ok']);
        $this->assertFalse($this->chooseAccount('support-off@example.com')['ok']);
    }
}
