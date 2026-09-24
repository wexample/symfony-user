<?php

namespace Wexample\SymfonyUser;

use Wexample\SymfonyHelpers\Class\AbstractBundle;
use Wexample\SymfonyHelpers\Helper\BundleHelper;
use Wexample\SymfonyHelpers\Interface\LoaderBundleInterface;

class WexampleSymfonyUserBundle extends AbstractBundle implements LoaderBundleInterface
{
    public static function getLoaderFrontPaths(): array
    {
        return [
            BundleHelper::getBundleCssAlias(static::class) => __DIR__ . '/../assets/',
        ];
    }
}
