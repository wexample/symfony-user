<?php

namespace Wexample\SymfonyUser\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Enum\AccountAction;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\AccountDirectoryService;

/**
 * What an administrator does to one account with a button: deactivate it and
 * put it back, lock it and unlock it, send it the mail to choose a password.
 * Each lands back on the account's page, with what happened in the query —
 * the page says it, the journal has already recorded it.
 *
 * The rules are AccountAdministrationService's; this only names the account
 * and the intent. Absent, as the pages are, where the application declared
 * no `administration.page_role`.
 */
final class AccountActionController extends AbstractController
{
    /** The query parameter the account's page reads the outcome from. */
    public const string PARAMETER_DONE = 'done';
    public const string PARAMETER_REFUSED = 'refused';

    /**
     * The id the page's buttons sign their token with: the one the
     * application already declares for its forms — `submit`, in
     * `framework.csrf_protection.stateless_token_ids` —, so that these
     * buttons ask for no second declaration.
     */
    public const string CSRF_TOKEN_ID = 'submit';

    #[Route(
        path: '/accounts/{id}/{action}',
        name: UserRoute::ACCOUNT_ACTION,
        methods: [Request::METHOD_POST]
    )]
    public function act(
        Request $request,
        string $id,
        AccountAction $action,
        AccountAdministrationService $administration,
        AccountDirectoryService $directory
    ): Response {
        if (! $administration->getPageRole()) {
            throw $this->createNotFoundException();
        }

        if (! $administration->canAdminister()) {
            throw $this->createAccessDeniedException();
        }

        // A button, not a form the browser would fill: the token is the only
        // thing telling a click on the page from a click somewhere else.
        if (! $this->isCsrfTokenValid(self::CSRF_TOKEN_ID, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if (! $target = $directory->find($id)) {
            throw $this->createNotFoundException();
        }

        /** @var AbstractUser $actor */
        $actor = $this->getUser();

        try {
            $this->apply($administration, $action, $actor, $target);
        } catch (AccountAdministrationException $exception) {
            return $this->redirectToRoute(UserRoute::ACCOUNT, [
                'id' => $id,
                self::PARAMETER_REFUSED => $exception->refusal->value,
            ]);
        }

        return $this->redirectToRoute(UserRoute::ACCOUNT, [
            'id' => $id,
            self::PARAMETER_DONE => $action->value,
        ]);
    }

    /**
     * @throws AccountAdministrationException
     */
    private function apply(
        AccountAdministrationService $administration,
        AccountAction $action,
        AbstractUser $actor,
        AbstractUser $target
    ): void {
        match ($action) {
            AccountAction::DEACTIVATE => $administration->deactivate($actor, $target),
            AccountAction::REACTIVATE => $administration->reactivate($actor, $target),
            AccountAction::LOCK => $administration->lock($actor, $target),
            AccountAction::UNLOCK => $administration->unlock($actor, $target),
            AccountAction::PASSWORD_MAIL => $administration->sendPasswordMail($actor, $target),
        };
    }
}
