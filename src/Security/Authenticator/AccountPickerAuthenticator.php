<?php

namespace Wexample\SymfonyUser\Security\Authenticator;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyUser\Form\AccountPickerForm;
use Wexample\SymfonyUser\Form\ImpersonateForm;
use Wexample\SymfonyUser\Security\Token\AccountPickerToken;
use Wexample\SymfonyUser\Service\AccountPickerService;
use Wexample\SymfonyUser\Service\FormProcessor\AccountPickerFormProcessor;
use Wexample\SymfonyUser\Service\PostLoginTargetService;

/**
 * Signs in the active account chosen in AccountPickerForm, with no password
 * and no second factor. Listing it in the firewall's `custom_authenticators`
 * is what turns the account picker on: an application whose users trust each
 * other, a demonstration. The account gates — terms, authenticator setup —
 * still apply.
 */
class AccountPickerAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AccountPickerService $accountPicker,
        private readonly AccountPickerFormProcessor $formProcessor,
        private readonly FormResponsePayloadBuilder $payloadBuilder,
        private readonly PostLoginTargetService $postLoginTarget,
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return $request->isMethod(Request::METHOD_POST)
            && $request->attributes->get('_route') === AbstractFormProcessor::FORM_SUBMIT_ROUTE
            && $request->attributes->get('name') === ClassHelper::longTableized(AccountPickerForm::class);
    }

    public function authenticate(Request $request): Passport
    {
        $formName = ClassHelper::getTableizedName(AccountPickerForm::class);
        $data = $request->request->all($formName);
        $identifier = trim((string) ($data[ImpersonateForm::FIELD_ACCOUNT] ?? ''));

        $badges = [];
        $formConfig = $this->formProcessor->createForm()->getConfig();
        if ($formConfig->getOption('csrf_protection')) {
            $badges[] = new CsrfTokenBadge(
                $formConfig->getAttribute('csrf_token_id', $formName),
                (string) ($data[$formConfig->getOption('csrf_field_name')] ?? '')
            );
        }

        return new SelfValidatingPassport(
            new UserBadge($identifier, fn (string $identifier) => $this->accountPicker->findAccount($identifier)
                ?? throw new UserNotFoundException()),
            $badges
        );
    }

    public function createToken(Passport $passport, string $firewallName): TokenInterface
    {
        return new AccountPickerToken($passport->getUser(), $firewallName, $passport->getUser()->getRoles());
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $this->formProcessor->redirect($this->postLoginTarget->getTargetUrl($request, $token, $firewallName));

        return new JsonResponse($this->payloadBuilder->build($this->formProcessor, $this->formProcessor->createForm()));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $form = $this->formProcessor->createForm();
        $this->formProcessor->addAuthenticationError($form, $exception);

        return new JsonResponse($this->payloadBuilder->build($this->formProcessor, $form));
    }
}
