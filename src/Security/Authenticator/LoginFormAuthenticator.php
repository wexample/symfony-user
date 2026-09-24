<?php

namespace Wexample\SymfonyUser\Security\Authenticator;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;

/**
 * Logs in from LoginForm, wherever the form is shown: its submission goes
 * through the forms bundle route like any other form, and this authenticator
 * takes it before the form processor would.
 *
 * A JSON caller gets the payload every ajax form reads, a page gets redirected.
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoginFormProcessor $loginFormProcessor,
        private readonly FormResponsePayloadBuilder $payloadBuilder,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request->isMethod(Request::METHOD_POST)
            && $request->attributes->get('_route') === AbstractFormProcessor::FORM_SUBMIT_ROUTE
            && $request->attributes->get('name') === ClassHelper::longTableized(LoginForm::class);
    }

    public function authenticate(Request $request): Passport
    {
        $formName = ClassHelper::getTableizedName(LoginForm::class);
        $data = $request->request->all($formName);
        $identifier = trim((string) ($data[LoginForm::FIELD_IDENTIFIER] ?? ''));

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        $rememberMe = new RememberMeBadge();
        if (! empty($data[LoginForm::FIELD_REMEMBER_ME])) {
            $rememberMe->enable();
        }

        $badges = [$rememberMe];

        // Checked the way the form itself would be, stateless token id included.
        $formConfig = $this->loginFormProcessor->createForm()->getConfig();
        if ($formConfig->getOption('csrf_protection')) {
            $badges[] = new CsrfTokenBadge(
                $formConfig->getAttribute('csrf_token_id', $formName),
                (string) ($data[$formConfig->getOption('csrf_field_name')] ?? '')
            );
        }

        return new Passport(
            new UserBadge($identifier),
            new PasswordCredentials((string) ($data[LoginForm::FIELD_PASSWORD] ?? '')),
            $badges
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        $session = $request->getSession();
        $url = $this->getTargetPath($session, $firewallName) ?: $request->getBasePath() . '/';
        $this->removeTargetPath($session, $firewallName);

        if (! RequestHelper::isJsonRequest($request)) {
            return new RedirectResponse($url);
        }

        $this->loginFormProcessor->redirect($url);

        return new JsonResponse(
            $this->payloadBuilder->build(
                $this->loginFormProcessor,
                $this->loginFormProcessor->createForm()
            )
        );
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): Response {
        if (! RequestHelper::isJsonRequest($request)) {
            $request->getSession()->set(SecurityRequestAttributes::AUTHENTICATION_ERROR, $exception);

            return new RedirectResponse($this->getFormPageUrl($request));
        }

        $form = $this->loginFormProcessor->createForm();
        $this->loginFormProcessor->addAuthenticationError($form, $exception);

        return new JsonResponse(
            $this->payloadBuilder->build($this->loginFormProcessor, $form)
        );
    }

    /**
     * An ajax call reaching a protected URL gets told where to log in,
     * instead of following a redirect to an HTML page it cannot use.
     */
    public function start(
        Request $request,
        ?AuthenticationException $authException = null
    ): Response {
        if (! RequestHelper::isJsonRequest($request)) {
            return parent::start($request, $authException);
        }

        return new JsonResponse(
            [
                'ok' => false,
                'action' => [
                    'type' => AbstractFormProcessor::ACTION_REDIRECT,
                    'url' => $this->getLoginUrl($request),
                ],
            ],
            Response::HTTP_UNAUTHORIZED
        );
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(SecurityController::ROUTE_LOGIN);
    }

    /**
     * The page that showed the form, so a login embedded elsewhere than on the
     * login page (a tunnel step) comes back to it with its error.
     */
    private function getFormPageUrl(Request $request): string
    {
        $referer = (string) $request->headers->get('referer');

        if ($referer !== '' && parse_url($referer, PHP_URL_HOST) === $request->getHost()) {
            return $referer;
        }

        return $this->getLoginUrl($request);
    }
}
