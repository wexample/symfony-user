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
use Wexample\SymfonyUser\Form\AccountRolesForm;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\AccountDirectoryService;
use Wexample\SymfonyUser\Service\AssignableRolesService;

/**
 * The roles of one account, from the administration screen. Which account it
 * is travels in the submission URL, as the entity form route of
 * symfony-forms does it, and never in a field of the form.
 *
 * The roles the account holds outside the ones the actor administers are
 * kept: the form never showed them, and a switch left off is not a role
 * taken away — AccountAdministrationService would refuse removing it anyway.
 *
 * As in AccountCreateFormProcessor, the change happens in formIsValid(), so
 * that a refusal comes back as an error on the form.
 */
class AccountRolesFormProcessor extends AbstractFormProcessor
{
    /** The refusal code is appended: `@form::error.refused.self_demotion`. */
    public const string ERROR_REFUSED = '@form::error.refused.';

    private ?AbstractUser $target = null;

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

    public function getFormActionRoute(): string
    {
        return 'form_' . FormController::ROUTE_ENTITY_FORM_PROCESSOR_SUBMIT;
    }

    /**
     * The id of the account, not of the switches the form is filled with:
     * which one is being edited is the processor's own state.
     */
    public function getFormActionArgs($data): array
    {
        return parent::getFormActionArgs($data) + ['id' => (string) $this->target->getId()];
    }

    /**
     * $data is the account being edited, not the form's own data: the form
     * holds one switch per role, filled from the roles the account holds.
     */
    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        $this->target = $data;
        $offered = $this->getOfferedRoles();
        $held = $data instanceof AbstractUser ? $data->getRoles() : [];

        return parent::createForm(
            array_combine(
                array_map(AccountRolesForm::getRoleFieldName(...), $offered),
                array_map(static fn (string $role) => in_array($role, $held, true), $offered)
            ),
            $options + [AccountRolesForm::OPTION_ROLES => $offered]
        );
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

        $offered = $this->getOfferedRoles();

        try {
            $this->administration->changeRoles(
                $this->getActor(),
                $this->target,
                [
                    ...array_values(array_diff($this->target->getRoles(), $offered)),
                    ...AccountRolesForm::getSubmittedRoles($form, $offered),
                ]
            );
        } catch (AccountAdministrationException $exception) {
            $form->addError(new FormError(self::ERROR_REFUSED . $exception->refusal->value));

            return false;
        }

        return true;
    }

    public function onValid(FormInterface $form)
    {
        $this->setNotification('@form::success.message');
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
