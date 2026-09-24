<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyUser\WexampleSymfonyUserBundle;

class KernelBootTest extends KernelTestCase
{
    public function testKernelBootsWithBundle(): void
    {
        self::bootKernel();

        $this->assertInstanceOf(
            WexampleSymfonyUserBundle::class,
            self::$kernel->getBundles()['WexampleSymfonyUserBundle']
        );
    }
}
