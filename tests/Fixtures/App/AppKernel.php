<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyForms\WexampleSymfonyFormsBundle;
use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;
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
            new SchebTwoFactorBundle(),
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyFormsBundle(),
            new WexampleSymfonyTunnelsBundle(),
            new WexampleSymfonyUserBundle(),
        ];
    }

    protected function getConfigFiles(): array
    {
        return [
            __DIR__ . '/config/config.yaml',
        ];
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import('@WexampleSymfonyFormsBundle/Resources/config/routes.yaml');
        $routes->import('@WexampleSymfonyTunnelsBundle/Resources/config/routes.yaml');
        $routes->import(__DIR__ . '/../../../src/Controller/', 'attribute');
        $routes->import(__DIR__ . '/Controller/', 'attribute');
    }
}
