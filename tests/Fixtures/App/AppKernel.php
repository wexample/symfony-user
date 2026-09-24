<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyUser\WexampleSymfonyUserBundle;

class AppKernel extends AbstractFixtureKernel
{
    protected function getFixtureDir(): string
    {
        return __DIR__;
    }

    protected function getExtraBundles(): iterable
    {
        return [
            new SecurityBundle(),
            new WexampleSymfonyUserBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            __DIR__ . '/config/config.yaml',
        ];
    }
}
