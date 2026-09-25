<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Form\SetPasswordForm;
use Wexample\SymfonyUser\Security\Authenticator\LoginFormAuthenticator;
use Wexample\SymfonyUser\Service\PasswordResetService;
use Wexample\SymfonyUser\Service\PasswordUpdaterService;

/**
 * Sets the password of the user the session holds a reset proof for, then
 * signs them in.
 */
class SetPasswordFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_NO_PROOF = '@form::error.no_proof';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly PasswordResetService $passwordResetService,
        private readonly PasswordUpdaterService $passwordUpdater,
        private readonly Security $security,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! $this->passwordResetService->getProofUser()) {
            $form->addError(new FormError(self::ERROR_NO_PROOF));

            return false;
        }

        return parent::formIsValid($form);
    }

    public function onValid(FormInterface $form)
    {
        $user = $this->passwordResetService->getProofUser();

        $this->passwordUpdater->update(
            $user,
            (string) $form->get(SetPasswordForm::FIELD_NEW_PASSWORD)->getData()
        );
        $this->passwordResetService->clearProof();

        if ($this->security->getUser() !== $user) {
            $this->security->login($user, LoginFormAuthenticator::class);
        }

        $this->setNotification('@form::success.message');
        $this->redirect($this->request?->getBasePath() . '/');
    }
}
