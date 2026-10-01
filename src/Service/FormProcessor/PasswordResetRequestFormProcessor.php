<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\SecurityEventType;
use Wexample\SymfonyUser\Form\PasswordResetRequestForm;
use Wexample\SymfonyUser\Service\MailRequestLimiterService;
use Wexample\SymfonyUser\Service\SecurityJournalService;
use Wexample\SymfonyUser\Service\PasswordResetService;

/**
 * Answers the same whether the account exists or not.
 */
class PasswordResetRequestFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly UserProviderInterface $userProvider,
        private readonly PasswordResetService $passwordResetService,
        private readonly SecurityJournalService $journal,
        private readonly MailRequestLimiterService $limiter,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    public function onValid(FormInterface $form)
    {
        $identifier = (string) $form->get(PasswordResetRequestForm::FIELD_IDENTIFIER)->getData();

        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            $user = null;
        }

        $limited = ! $this->limiter->consume($user?->getUserIdentifier() ?? $identifier);

        // What the answer hides, the journal keeps: an unknown address as a
        // fingerprint, an account that gets nothing with why.
        $this->journal->record(
            SecurityEventType::PASSWORD_RESET_REQUESTED,
            $user,
            $limited ? 'rate_limited' : $this->getRefusalCause($user),
            extra: $user ? [] : ['identifier_fingerprint' => $this->journal->fingerprint($identifier)]
        );

        if (! $limited && $user instanceof AbstractUser) {
            $this->passwordResetService->sendResetLink($user);
        }

        $this->setNotification('@form::success.message');
    }

    private function getRefusalCause(mixed $user): ?string
    {
        return match (true) {
            ! $user instanceof AbstractUser => 'unknown_user',
            $user->isLocked() => 'locked',
            ! $user->isEnabled() => 'disabled',
            default => null,
        };
    }
}
