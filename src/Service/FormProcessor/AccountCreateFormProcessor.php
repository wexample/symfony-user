<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Form\AccountCreateForm;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\AccountDirectoryService;
use Wexample\SymfonyUser\Service\AssignableRolesService;

/**
 * Opens an account from the administration screen: the application's user
 * class, the address typed, the roles switched on, then
 * AccountAdministrationService, which writes it without a password and sends
 * its holder the mail to choose one.
 *
 * The writing happens in formIsValid() rather than in onValid(): most of
 * what the rules refuse is only known by trying, and a refusal has to come
 * back as an error on the form — past onValid() the answer is already a
 * success. onValid() is left with where to go next.
 */
class AccountCreateFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_EMAIL_TAKEN = '@form::error.email_taken';

    /** The refusal code is appended: `@form::error.refused.exclusive_roles`. */
    public const string ERROR_REFUSED = '@form::error.refused.';

    private ?AbstractUser $created = null;

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly AccountAdministrationService $administration,
        private readonly AccountDirectoryService $directory,
        private readonly AssignableRolesService $assignableRoles,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        return parent::createForm($data, $options + [
            AccountCreateForm::OPTION_ROLES => $this->getOfferedRoles(),
        ]);
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! $this->administration->canAdminister()) {
            throw new AccessDeniedHttpException('Administering accounts is not this account\'s.');
        }

        if (! parent::formIsValid($form)) {
            return false;
        }

        $email = (string) $form->get(AccountCreateForm::FIELD_EMAIL)->getData();

        // The unique column would answer this one with a database error.
        if ($this->directory->findByIdentifier($email)) {
            $form->get(AccountCreateForm::FIELD_EMAIL)->addError(new FormError(self::ERROR_EMAIL_TAKEN));

            return false;
        }

        $class = $this->directory->getUserClass();
        $account = (new $class())
            ->setEmail($email)
            ->setRoles(AccountCreateForm::getSubmittedRoles($form, $this->getOfferedRoles()));

        try {
            $this->administration->createAccount($this->getActor(), $account);
        } catch (AccountAdministrationException $exception) {
            $form->addError(new FormError(self::ERROR_REFUSED . $exception->refusal->value));

            return false;
        }

        $this->created = $account;

        return true;
    }

    public function onValid(FormInterface $form)
    {
        $this->setNotification('@form::success.message');
        $this->redirectToRoute(UserRoute::ACCOUNT, ['id' => (string) $this->created->getId()]);
    }

    /**
     * @return list<string>
     */
    private function getOfferedRoles(): array
    {
        return $this->assignableRoles->getAssignableRoles($this->getActor());
    }

    private function getActor(): AbstractUser
    {
        /** @var AbstractUser $actor the processor is behind canAdminister() */
        $actor = $this->security->getUser();

        return $actor;
    }
}
