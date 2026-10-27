<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Controller\FormController;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Form\AccountEditForm;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\AccountDirectoryService;

/**
 * What an account is called, from the administration screen. Which account
 * it is travels in the submission URL, as AccountRolesFormProcessor does it.
 *
 * As in AccountCreateFormProcessor, the change happens in formIsValid(), so
 * that an address or a username already taken, or a refusal, comes back as
 * an error on the form.
 */
class AccountEditFormProcessor extends AbstractFormProcessor
{
    public const string ERROR_EMAIL_TAKEN = '@form::error.email_taken';
    public const string ERROR_USERNAME_TAKEN = '@form::error.username_taken';

    /** The refusal code is appended: `@form::error.refused.target_out_of_scope`. */
    public const string ERROR_REFUSED = '@form::error.refused.';

    private ?AbstractUser $target = null;

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly AccountAdministrationService $administration,
        private readonly AccountDirectoryService $directory,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    /**
     * The account of the submission handled, not of the next one.
     */
    public function reset(): void
    {
        parent::reset();
        $this->target = null;
    }

    public function getFormActionRoute(): string
    {
        return 'form_' . FormController::ROUTE_ENTITY_FORM_PROCESSOR_SUBMIT;
    }

    public function getFormActionArgs($data): array
    {
        return parent::getFormActionArgs($data) + ['id' => (string) $this->target->getId()];
    }

    /**
     * $data is the account being edited; the form is filled from it.
     */
    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        $this->target = $data;
        $named = $this->isNamed();

        $filled = [
            AccountEditForm::FIELD_EMAIL => $data?->getEmail(),
            AccountEditForm::FIELD_USERNAME => $data?->getUsername(),
        ];

        if ($named) {
            $filled[AccountEditForm::FIELD_FIRST_NAME] = $data->getFirstName();
            $filled[AccountEditForm::FIELD_LAST_NAME] = $data->getLastName();
        }

        return parent::createForm($filled, $options + [AccountEditForm::OPTION_NAMED => $named]);
    }

    /**
     * The account the submission URL names, which createForm() is then
     * handed as its data.
     */
    public function handleSubmittedRequest(Request $request): AbstractUser
    {
        $target = $this->directory->find((string) $request->attributes->get('id'));

        if (! $target) {
            throw new NotFoundHttpException('No account under this id.');
        }

        return $target;
    }

    public function formIsValid(FormInterface $form): bool
    {
        if (! $this->administration->canAdminister()) {
            throw new AccessDeniedHttpException('Administering accounts is not this account\'s.');
        }

        if (! parent::formIsValid($form)) {
            return false;
        }

        $email = (string) $form->get(AccountEditForm::FIELD_EMAIL)->getData();
        $username = $form->get(AccountEditForm::FIELD_USERNAME)->getData();

        // The unique columns would answer these with a database error.
        $taken = false;
        foreach ([AccountEditForm::FIELD_EMAIL => self::ERROR_EMAIL_TAKEN, AccountEditForm::FIELD_USERNAME => self::ERROR_USERNAME_TAKEN] as $field => $error) {
            $holder = $this->directory->findByIdentifier((string) $form->get($field)->getData());

            if ($holder && ! $holder->getId()->equals($this->target->getId())) {
                $form->get($field)->addError(new FormError($error));
                $taken = true;
            }
        }

        if ($taken) {
            return false;
        }

        $names = [];
        if ($form->has(AccountEditForm::FIELD_FIRST_NAME)) {
            $names = [
                'first_name' => $form->get(AccountEditForm::FIELD_FIRST_NAME)->getData(),
                'last_name' => $form->get(AccountEditForm::FIELD_LAST_NAME)->getData(),
            ];
        }

        try {
            $this->administration->edit($this->getActor(), $this->target, $email, $username, $names);
        } catch (AccountAdministrationException $exception) {
            $form->addError(new FormError(self::ERROR_REFUSED . $exception->refusal->value));

            return false;
        }

        return true;
    }

    /**
     * Back to the account's page, which then shows what changed: the modal
     * closes on it.
     */
    public function onValid(FormInterface $form)
    {
        $this->setNotification('@form::success.message');
        $this->redirectToRoute(UserRoute::ACCOUNT, ['id' => (string) $this->target->getId()]);
    }

    /**
     * Read on the object rather than on the trait, as ProfileFormProcessor
     * does: an application may hold the names in a class of its own.
     */
    private function isNamed(): bool
    {
        return $this->target !== null && method_exists($this->target, 'getFirstName');
    }

    private function getActor(): AbstractUser
    {
        /** @var AbstractUser $actor the processor is behind canAdminister() */
        $actor = $this->security->getUser();

        return $actor;
    }
}
