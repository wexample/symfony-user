<?php

namespace Wexample\SymfonyUser\Service\Tunnel\Step;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTunnels\Class\TunnelCursor;
use Wexample\SymfonyTunnels\Service\Step\AbstractFormTunnelStep;
use Wexample\SymfonyTunnels\Service\Step\AbstractTunnelStep;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\Tunnel\UserMailForm;
use Wexample\SymfonyUser\Service\FormProcessor\Tunnel\UserMailFormProcessor;

/**
 * Asks who the visitor is, by their email, without asking them to sign in
 * first — a checkout, a sign-up. What follows depends on the address:
 *
 * - signed in already: their account, then the step after;
 * - an activated account: the login step, then the step after;
 * - an account not activated yet, or no account: one is created, not
 *   activated, and the visitor goes on as it. Created once per tunnel session,
 *   whatever the back button does.
 *
 * The application says which step comes after, and how its user is created.
 */
abstract class AbstractUserMailStep extends AbstractFormTunnelStep
{
    /**
     * The identifier of the account the tunnel goes on with.
     */
    public const string VARIABLE_USER = 'user-mail-user';

    /**
     * The identifier of the account this tunnel session created, if any.
     */
    public const string VARIABLE_CREATED_USER = 'user-mail-created-user';

    public function __construct(
        protected readonly UserMailFormProcessor $formProcessor,
        protected readonly LoginStep $loginStep,
        protected readonly Security $security,
        protected readonly UserProviderInterface $userProvider,
        protected readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getName(): string
    {
        return 'user-mail';
    }

    /**
     * Where the visitor goes once known, through the login step or not.
     */
    abstract protected function getStepAfter(TunnelCursor $cursor): AbstractTunnelStep;

    /**
     * A new account for $email, not activated. It is persisted by the step.
     */
    abstract protected function createUser(string $email): AbstractUser;

    /**
     * The account is known: attach it to what the tunnel builds (a cart).
     */
    protected function onUserResolved(AbstractUser $user, TunnelCursor $cursor): void
    {
    }

    public function getFormProcessor(TunnelCursor $cursor): AbstractFormProcessor
    {
        return $this->formProcessor;
    }

    public function getAllowedNextSteps(TunnelCursor $cursor): array
    {
        return [$this->loginStep, $this->getStepAfter($cursor)];
    }

    /**
     * The login step leads to the same place as this one.
     */
    public function alterNextStepAllowedFollowings(
        AbstractTunnelStep $nextStep,
        array $allowed,
        TunnelCursor $cursor,
    ): array {
        return $nextStep === $this->loginStep ? [$this->getStepAfter($cursor)] : $allowed;
    }

    /**
     * The account the tunnel goes on with, for the steps after: the signed-in
     * one, or the one this step found or created.
     */
    public function getTunnelUser(TunnelCursor $cursor): ?AbstractUser
    {
        $user = $this->security->getUser();

        if ($user instanceof AbstractUser) {
            return $user;
        }

        $identifier = $cursor->manager->getVariableValue(self::VARIABLE_USER);

        return $identifier ? $this->loadUser($identifier) : null;
    }

    public function buildFormData(TunnelCursor $cursor): mixed
    {
        return [UserMailForm::FIELD_EMAIL => $this->getTunnelUser($cursor)?->getEmail()];
    }

    public function onFormValid(
        FormInterface $form,
        TunnelCursor $cursor,
    ): ?TunnelCursor {
        $user = $this->security->getUser();
        $nextStep = $this->getStepAfter($cursor);

        if (! $user instanceof AbstractUser) {
            $email = mb_strtolower(trim((string) $form->get(UserMailForm::FIELD_EMAIL)->getData()));
            $user = $this->loadUser($email);

            if ($user && $user->isEnabled()) {
                // Theirs to prove: the login step, the address typed already.
                $cursor->manager->setVariableValue(LoginStep::VARIABLE_IDENTIFIER, $email);
                $nextStep = $this->loginStep;
            } elseif (! $user) {
                $user = $this->reuseOrCreateUser($email, $cursor);
            }
        }

        $cursor->manager->setVariableValue(self::VARIABLE_USER, $user->getUserIdentifier());
        $this->onUserResolved($user, $cursor);

        $next = $cursor->findFirstNextByStep($nextStep);
        $cursor->setComplete($next);

        return $next;
    }

    /**
     * Back to this step with another address: the account this session created
     * is not activated, its address can still change.
     */
    private function reuseOrCreateUser(string $email, TunnelCursor $cursor): AbstractUser
    {
        $createdIdentifier = $cursor->manager->getVariableValue(self::VARIABLE_CREATED_USER);
        $user = $createdIdentifier ? $this->loadUser($createdIdentifier) : null;

        if ($user && ! $user->isEnabled()) {
            $user->setEmail($email);
        } else {
            $user = $this->createUser($email)->setEmail($email)->setEnabled(false);
            $this->entityManager->persist($user);
        }

        $this->entityManager->flush();
        $cursor->manager->setVariableValue(self::VARIABLE_CREATED_USER, $user->getUserIdentifier());

        return $user;
    }

    private function loadUser(string $identifier): ?AbstractUser
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            return null;
        }

        return $user instanceof AbstractUser ? $user : null;
    }
}
