<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Wexample\SymfonyUser\Tests\Fixtures\App\ImpersonationAnyAppKernel;

/**
 * `targets: any`, as in development: every account, above the actor or out
 * of the application's scope included — never oneself or a disabled one.
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
            ['outsider-support@example.com', 'owner@example.com', 'support-a@example.com', 'support-b@example.com'],
            // Four, past the threshold of three: found by a search.
            $this->identifiers($this->getService()->searchTargets($this->find('manager'), 'example'))
        );

        $this->login('manager');
        $this->assertTrue($this->chooseAccount('owner@example.com')['ok']);
        $this->assertFalse($this->chooseAccount('support-off@example.com')['ok']);
    }
}
