<?php

namespace Wexample\SymfonyUser\Service\Tunnel\TotpSetup;

use Wexample\SymfonyTunnels\Class\TunnelCursor;

/**
 * What the three steps share: their label, read in the authenticator page's
 * own words.
 */
trait TotpSetupStepTrait
{
    public const string TRANSLATION_DOMAIN = 'WexampleSymfonyUserBundle.pages.totp.index::';

    public function buildLabel(TunnelCursor $cursor): string
    {
        return $this->translator->trans(self::TRANSLATION_DOMAIN.'steps.'.static::getName());
    }
}
