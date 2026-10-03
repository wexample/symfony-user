<?php

namespace Wexample\SymfonyUser\Tests\Fixtures\App;

/**
 * The fixture application whose firewall lists AccountPickerAuthenticator.
 */
class AccountPickerAppKernel extends AppKernel
{
    protected function getConfigFiles(): array
    {
        return [
            ...parent::getConfigFiles(),
            __DIR__ . '/config/account_picker.yaml',
        ];
    }
}
