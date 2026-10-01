<?php

namespace Wexample\SymfonyUser\Service\Tunnel\TotpSetup;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\Translation\TranslatorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractFormTunnelStep;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\FormProcessor\TotpEnableFormProcessor;

/**
 * The first code the app shows, proving it holds the key: the processor turns
 * the app on when it is right. Once on, there is nothing left to prove here.
 */
class TotpVerifyStep extends AbstractFormTunnelStep
{
    use TotpSetupStepTrait;

    public function __construct(
        protected readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly TotpEnableFormProcessor $formProcessor,
        private readonly TotpCodesStep $codesStep,
    ) {
    }

    public static function getName(): string
    {
        return 'verify';
    }

    public function getFormProcessor(TunnelCursor $cursor): AbstractFormProcessor
    {
        return $this->formProcessor;
    }

    public function buildSubmitLabel(TunnelCursor $cursor): string
    {
        return self::TRANSLATION_DOMAIN.'steps.activate';
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [$this->codesStep];
    }

    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($redirect = parent::needsRedirect($cursor)) {
            return $redirect;
        }

        $user = $this->security->getUser();

        if ($user instanceof AbstractUser && $user->isTotpAuthenticationEnabled()) {
            $next = $cursor->manager->selectNextCursor($cursor);
            $cursor->setComplete($next);

            return $next;
        }

        return null;
    }
}
