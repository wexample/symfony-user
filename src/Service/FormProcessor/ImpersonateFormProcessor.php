<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\ImpersonateForm;
use Wexample\SymfonyUser\Service\ImpersonationService;
use Wexample\SymfonyUser\Service\PostLoginTargetService;

/**
 * Checks the account chosen against the rules, remembers the choice — the
 * switch requires it —, then sends the browser to the firewall's switch URL,
 * on the page the account lands on. Works while impersonating: the firewall
 * leaves the current account before taking the next.
 * An unknown account and a refused one read the same.
 */
class ImpersonateFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_NOT_ALLOWED = '@form::error.not_allowed';

    private ?AbstractUser $target = null;

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly ImpersonationService $impersonation,
        private readonly PostLoginTargetService $postLoginTarget,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! parent::formIsValid($form)) {
            return false;
        }

        $actor = $this->impersonation->getActor();
        $this->target = $actor && $this->impersonation->canImpersonate()
            ? $this->impersonation->findTarget($actor, (string) $form->get(ImpersonateForm::FIELD_ACCOUNT)->getData())
            : null;

        if (! $this->target) {
            $form->addError(new FormError(self::ERROR_NOT_ALLOWED));

            return false;
        }

        return true;
    }

    public function onValid(FormInterface $form)
    {
        $this->impersonation->grantIntent($this->target);

        // The switch happens on the page the account lands on once signed in,
        // where the firewall then sends the browser back, as that account.
        $url = $this->postLoginTarget->getRolesUrl($this->request, $this->target->getRoles());

        $this->redirect($url . (str_contains($url, '?') ? '&' : '?') . http_build_query([
            $this->impersonation->getSwitchUserConfig()['parameter'] => $this->target->getUserIdentifier(),
        ]));
    }
}
