<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

use DH\AuditorBundle\DHAuditorBundle;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;

/**
 * The fixture application keeping the history of its accounts: the
 * `security` category of symfony-activity enabled.
 */
class ActivityAppKernel extends AppKernel
{
    protected function getExtraBundles(): iterable
    {
        yield from parent::getExtraBundles();
        // symfony-activity records field changes through auditor-bundle, and
        // refuses to boot without it.
        yield new DHAuditorBundle();
        yield new WexampleSymfonyActivityBundle();
    }

    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/activity.yaml',
        ];
    }
}
