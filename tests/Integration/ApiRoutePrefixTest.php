<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyApi\Tests\Traits\TestCase\Application\ApiRoutePrefixTestCaseTrait;
use Wexample\SymfonyUser\Tests\Fixtures\App\AdministrationAppKernel;

/**
 * The package's api routes hold symfony-api's prefixes: what the pages read
 * on the session under `/_api/`, behind the session firewall.
 */
class ApiRoutePrefixTest extends WebTestCase
{
    use ApiRoutePrefixTestCaseTrait;

    protected static function getKernelClass(): string
    {
        return AdministrationAppKernel::class;
    }

    public function testApiRoutesFollowThePrefixNorm(): void
    {
        $this->assertApiRoutesFollowPrefixNorm();
    }
}
