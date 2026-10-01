<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\SwitchUserToken;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TermsAcceptForm;
use Wexample\SymfonyUser\Service\TermsService;

class TermsAcceptFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_OUTDATED = '@form::error.outdated';
    public const string ERROR_IMPERSONATION = '@form::error.impersonation';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly TermsService $termsService,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! parent::formIsValid($form)) {
            return false;
        }

        // Only the user accepts, never an administrator in their place.
        if ($this->security->getToken() instanceof SwitchUserToken || ! $this->security->getUser() instanceof AbstractUser) {
            $form->addError(new FormError(self::ERROR_IMPERSONATION));

            return false;
        }

        if ($form->get(TermsAcceptForm::FIELD_VERSION)->getData() !== $this->termsService->getVersion()) {
            $form->addError(new FormError(self::ERROR_OUTDATED));

            return false;
        }

        return true;
    }

    public function onValid(FormInterface $form)
    {
        $this->termsService->accept($this->security->getUser(), $this->termsService->getVersion());

        $this->redirect($this->request?->getBasePath() . '/');
    }
}
