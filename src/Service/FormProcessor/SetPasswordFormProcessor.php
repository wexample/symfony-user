<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
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
use Wexample\SymfonyUser\Service\PostLoginTargetService;

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
        private readonly PostLoginTargetService $postLoginTarget,
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

        // A forced change is the user choosing their own password, not a
        // reset: the journal tells them apart.
        $this->passwordUpdater->update(
            $user,
            (string) $form->get(SetPasswordForm::FIELD_NEW_PASSWORD)->getData(),
            reset: $this->passwordResetService->hasProof()
        );
        $this->passwordResetService->clearProof();

        if ($this->security->getUser() !== $user) {
            $this->security->login($user, LoginFormAuthenticator::class);
        }

        $this->setNotification('@form::success.message');
        $this->redirect($this->getTargetUrl());
    }

    /**
     * Signed in by the reset, or still to pass a second factor: scheb holds
     * any page until it is passed.
     */
    private function getTargetUrl(): string
    {
        $token = $this->security->getToken();

        return $token instanceof TwoFactorTokenInterface
            ? $this->request->getBasePath() . '/'
            : $this->postLoginTarget->getTargetUrl($this->request, $token, $this->security->getFirewallConfig($this->request)->getName());
    }
}
