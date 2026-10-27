<?php

namespace Wexample\SymfonyUser;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Interface\LoaderBundleInterface;
use Wexample\SymfonySecurity\Helper\SecurityBundleHelper;
use Wexample\SymfonyUser\DependencyInjection\Compiler\DefaultUserMappingPass;

class WexampleSymfonyUserBundle extends AbstractBundle implements LoaderBundleInterface
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        SecurityBundleHelper::assertRegistered($container, static::class);
        $container->addCompilerPass(new DefaultUserMappingPass());
    }

    public static function getLoaderFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__ . '/../assets/',
        ];
    }
}
