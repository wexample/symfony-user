<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
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
use Wexample\SymfonyUser\Form\MagicLinkRequestForm;
use Wexample\SymfonyUser\Service\MagicLinkService;
use Wexample\SymfonyUser\Service\MailRequestLimiterService;
use Wexample\SymfonyUser\Service\SecurityJournalService;

/**
 * Answers the same whether the account exists or not, so the form never
 * tells which accounts exist.
 */
class MagicLinkRequestFormProcessor extends AbstractFormProcessor
{
    public const string MESSAGE_SENT = '@form::success.message';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly UserProviderInterface $userProvider,
        private readonly MagicLinkService $magicLinkService,
        private readonly SecurityJournalService $journal,
        private readonly MailRequestLimiterService $limiter,
        #[Autowire(param: 'wexample_symfony_user.magic_link_login')]
        private readonly bool $enabled = true,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    /**
     * Off by `magic_link_login`, the form is not shown, and a submission
     * crafted anyway sends nothing.
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function onValid(FormInterface $form)
    {
        $identifier = (string) $form->get(MagicLinkRequestForm::FIELD_IDENTIFIER)->getData();

        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            $user = null;
        }

        $cause = match (true) {
            ! $this->enabled => 'magic_link_login_disabled',
            ! $this->limiter->consume($user?->getUserIdentifier() ?? $identifier) => 'rate_limited',
            default => $this->getRefusalCause($user),
        };

        // What the answer hides, the journal keeps: an unknown address as a
        // fingerprint, an account that gets nothing with why.
        $this->journal->record(
            SecurityEventType::MAGIC_LINK_REQUESTED,
            $user,
            $cause,
            extra: $user ? [] : ['identifier_fingerprint' => $this->journal->fingerprint($identifier)]
        );

        if ($this->enabled && $cause !== 'rate_limited' && $user instanceof AbstractUser) {
            $this->magicLinkService->sendLink($user);
        }

        $this->setNotification(self::MESSAGE_SENT);
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
