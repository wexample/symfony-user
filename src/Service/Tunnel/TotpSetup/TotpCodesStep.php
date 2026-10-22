<?php

namespace Wexample\SymfonyUser\Service\Tunnel\TotpSetup;

use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyUser\Service\TotpService;

/**
 * The backup codes, shown once: nothing keeps them in clear, so a second look
 * finds none — and says where new ones are made. The end of the tunnel.
 */
class TotpCodesStep extends AbstractTunnelStep
{
    use TotpSetupStepTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly TotpService $totpService,
    ) {
    }

    public static function getName(): string
    {
        return 'codes';
    }

    public function initAsCurrentStep(TunnelCursor $cursor): void
    {
        parent::initAsCurrentStep($cursor);

        $cursor->manager->setSessionComplete();
    }

    public function buildViewParams(TunnelCursor $cursor): array
    {
        return ['codes' => $this->totpService->pullNewBackupCodes()];
    }
}
