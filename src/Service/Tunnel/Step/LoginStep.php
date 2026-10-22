<?php

namespace Wexample\SymfonyUser\Service\Tunnel\Step;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Enum\TunnelStepCompleteStrategy;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyTunnels\Service\TunnelRoutingService;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;

/**
 * Signs the visitor in, then lets them on. A signed-in visitor goes straight
 * through. The login form is the package's own: it posts to the firewall, and
 * comes back to this step, saved as the target path, second factor included.
 *
 * What follows is said by the step before it (`alterNextStepAllowedFollowings()`),
 * or by an application subclass overriding `getAllowedNextSteps()`.
 */
class LoginStep extends AbstractTunnelStep
{
    use TargetPathTrait;

    /**
     * The identifier to type, set by a step before, UserMailStep for one.
     */
    public const string VARIABLE_IDENTIFIER = 'login-identifier';

    public function __construct(
        protected readonly LoginFormProcessor $loginFormProcessor,
        protected readonly Security $security,
        protected readonly RequestStack $requestStack,
        protected readonly TunnelRoutingService $tunnelRoutingService,
    ) {
    }

    public static function getName(): string
    {
        return 'login';
    }

    /**
     * The firewall the login form signs into.
     */
    protected function getFirewallName(): string
    {
        return 'main';
    }

    /**
     * Done when it sends a signed-in visitor on.
     */
    public function completeStrategy(): TunnelStepCompleteStrategy
    {
        return TunnelStepCompleteStrategy::ON_NEXT_REDIRECT;
    }

    public function needsRedirect(TunnelCursor $cursor): null|RedirectResponse|TunnelCursor
    {
        if ($redirect = parent::needsRedirect($cursor)) {
            return $redirect;
        }

        if ($this->security->isGranted('IS_AUTHENTICATED_FULLY')) {
            return $cursor->manager->selectNextCursor($cursor);
        }

        return null;
    }

    public function initAsCurrentStep(TunnelCursor $cursor): void
    {
        // Wherever the login ends — the form, the code of the second factor —
        // it comes back here, to be sent on.
        $this->saveTargetPath(
            $this->requestStack->getSession(),
            $this->getFirewallName(),
            $this->tunnelRoutingService->buildCursorUrl($cursor)
        );

        parent::initAsCurrentStep($cursor);
    }

    public function buildViewParams(TunnelCursor $cursor): array
    {
        return [
            'login_form' => $this->loginFormProcessor
                ->createForm([
                    LoginForm::FIELD_IDENTIFIER => $cursor->manager->getVariableValue(self::VARIABLE_IDENTIFIER),
                ])
                ->createView(),
        ];
    }
}
