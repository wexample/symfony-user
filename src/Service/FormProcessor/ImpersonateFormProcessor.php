<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\ImpersonateForm;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\ImpersonationService;

/**
 * Checks the account chosen against the rules, remembers the choice — the
 * switch requires it —, then sends the browser to the firewall's switch URL,
 * on the impersonation page.
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
        private readonly Security $security,
        private readonly ImpersonationService $impersonation,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! parent::formIsValid($form)) {
            return false;
        }

        $actor = $this->security->getUser();
        $this->target = $actor instanceof AbstractUser && $this->impersonation->canImpersonate()
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

        // The switch lands back on the impersonation page, now telling how to
        // leave: a page every signed-in account opens.
        $this->redirect($this->urlGenerator->generate(UserRoute::IMPERSONATE, [
            $this->impersonation->getSwitchUserConfig()['parameter'] => $this->target->getUserIdentifier(),
        ]));
    }
}
