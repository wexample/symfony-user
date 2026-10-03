<?php

namespace Wexample\SymfonyUser\Service\Tunnel\TotpSetup;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Service\TwoFactorPolicyService;

/**
 * The code to scan, and the key to type when scanning is not an option. An
 * app already set up has nothing to scan: the visitor is sent to its page.
 */
class TotpScanStep extends AbstractTunnelStep
{
    use TotpSetupStepTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly TotpService $totpService,
        private readonly TwoFactorPolicyService $policy,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TotpVerifyStep $verifyStep,
    ) {
    }

    public static function getName(): string
    {
        return 'scan';
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [$this->verifyStep];
    }

    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($redirect = parent::needsRedirect($cursor)) {
            return $redirect;
        }

        if ($this->getUser()->isTotpAuthenticationEnabled()) {
            return new RedirectResponse($this->urlGenerator->generate(UserRoute::TOTP));
        }

        return null;
    }

    public function buildViewParams(TunnelCursor $cursor): array
    {
        $user = $this->getUser();

        return [
            'required' => $this->policy->requiresApp($user),
            'qr_code' => $this->totpService->getPendingQrCodeDataUri($user),
            'secret' => $this->totpService->getPendingSecret(),
        ];
    }

    private function getUser(): AbstractUser
    {
        /** @var AbstractUser */
        return $this->security->getUser();
    }
}
