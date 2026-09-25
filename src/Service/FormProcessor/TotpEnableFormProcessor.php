<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Controller\Pages\TotpController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\TotpEnableForm;
use Wexample\SymfonyUser\Service\TotpService;

class TotpEnableFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly TotpService $totpService,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! parent::formIsValid($form)) {
            return false;
        }

        $user = $this->security->getUser();

        if (! $user instanceof AbstractUser
            || ! $this->totpService->confirm($user, (string) $form->get(TotpEnableForm::FIELD_CODE)->getData())) {
            $form->addError(new FormError('@form::error.invalid'));

            return false;
        }

        return true;
    }

    public function onValid(FormInterface $form)
    {
        // The backup codes were just made: shown once, on their own page.
        $this->redirectToRoute(TotpController::ROUTE_BACKUP_CODES);
    }
}
