<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use LogicException;
use PHPUnit\Framework\TestCase;
use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;
use Wexample\SymfonyUser\Tests\Fixtures\App\NoSecurityBundleAppKernel;
use Wexample\SymfonyUser\WexampleSymfonyUserBundle;

/**
 * Without symfony-security, link signatures would reach the logs unmasked:
 * the container refuses to build rather than run that way.
 */
class SecurityBundleRequiredTest extends TestCase
{
    public function testTheKernelFailsWithoutTheSecurityBundle(): void
    {
        $kernel = new NoSecurityBundleAppKernel('test', true);

        try {
            $this->expectException(LogicException::class);
            $this->expectExceptionMessage(WexampleSymfonyUserBundle::class . ' requires ' . WexampleSymfonySecurityBundle::class);

            $kernel->boot();
        } finally {
            $kernel->shutdown();
        }
    }
}
