<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use Wexample\SymfonySecurity\WexampleSymfonySecurityBundle;

/**
 * An application that forgot to register symfony-security.
 */
class NoSecurityBundleAppKernel extends AppKernel
{
    protected function getExtraBundles(): iterable
    {
        foreach (parent::getExtraBundles() as $bundle) {
            if (! $bundle instanceof WexampleSymfonySecurityBundle) {
                yield $bundle;
            }
        }
    }
}
