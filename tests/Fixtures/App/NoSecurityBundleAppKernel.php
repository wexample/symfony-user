<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Wexample\SymfonyApi\WexampleSymfonyApiBundle;
use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;

/**
 * An application that forgot to register symfony-security. symfony-api,
 * which asks for it too and is checked first, is left out: what fails is
 * this package's own check.
 */
class NoSecurityBundleAppKernel extends AppKernel
{
    protected function getExtraBundles(): iterable
    {
        foreach (parent::getExtraBundles() as $bundle) {
            if (! $bundle instanceof WexampleSymfonySecurityBundle && ! $bundle instanceof WexampleSymfonyApiBundle) {
                yield $bundle;
            }
        }
    }
}
