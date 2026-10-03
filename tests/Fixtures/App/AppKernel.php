<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Scheb\TwoFactorBundle\SchebTwoFactorBundle;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Wexample\SymfonyForms\WexampleSymfonyFormsBundle;
use Wexample\SymfonyLoader\WexampleSymfonyLoaderBundle;
use Wexample\SymfonyMail\WexampleSymfonyMailBundle;
use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;
use Wexample\SymfonyTesting\Tests\Fixtures\AbstractFixtureKernel;
use Wexample\SymfonyTranslations\WexampleSymfonyTranslationsBundle;
use Wexample\SymfonyTunnels\WexampleSymfonyTunnelsBundle;
use Wexample\SymfonyUser\WexampleSymfonyUserBundle;
use Wexample\SymfonyUserDs\WexampleSymfonyUserDsBundle;

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
            new MonologBundle(),
            new WexampleSymfonySecurityBundle(),
            new SchebTwoFactorBundle(),
            new WexampleSymfonyLoaderBundle(),
            new WexampleSymfonyTranslationsBundle(),
            new WexampleSymfonyMailBundle(),
            new WexampleSymfonyFormsBundle(),
            new WexampleSymfonyTunnelsBundle(),
            new WexampleSymfonyUserBundle(),
            // The screens, so the walks reach real pages.
            new WexampleSymfonyUserDsBundle(),
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
        $routes->import(dirname((new \ReflectionClass(WexampleSymfonyUserDsBundle::class))->getFileName()) . '/Controller/', 'attribute');
        $routes->import(__DIR__ . '/Controller/', 'attribute');
    }
}
