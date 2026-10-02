<?php

namespace Wexample\SymfonyUser\Security\Authenticator;

use Scheb\TwoFactorBundle\Security\Authentication\Token\TwoFactorTokenInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\PasswordHasher\PasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Wexample\Helpers\Helper\ClassHelper;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\SecurityController;
use Wexample\SymfonyUser\Controller\Pages\TwoFactorController;
use Wexample\SymfonyUser\Form\LoginForm;
use Wexample\SymfonyUser\Service\FormProcessor\LoginFormProcessor;
use Wexample\SymfonyUser\Service\PostLoginTargetService;

/**
 * Logs in from LoginForm, wherever the form is shown: its submission goes
 * through the forms bundle route like any other form, and this authenticator
 * takes it before the form processor would.
 *
 * A JSON caller gets the payload every ajax form reads, a page gets redirected.
 */
class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    private ?string $timingShieldHash = null;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LoginFormProcessor $loginFormProcessor,
        private readonly FormResponsePayloadBuilder $payloadBuilder,
        private readonly UserProviderInterface $userProvider,
        private readonly PasswordHasherFactoryInterface $passwordHasherFactory,
        private readonly PostLoginTargetService $postLoginTarget,
    ) {
    }

    public function supports(Request $request): bool
    {
        return self::isLoginRequest($request);
    }

    /**
     * The submission of LoginForm, whatever page showed it.
     */
    public static function isLoginRequest(Request $request): bool
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
        $password = (string) ($data[LoginForm::FIELD_PASSWORD] ?? '');

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
            new UserBadge($identifier, fn (string $identifier): UserInterface => $this->loadUser($identifier, $password)),
            new PasswordCredentials($password),
            $badges
        );
    }

    /**
     * An unknown address costs a password check too: without one, it would
     * answer faster than a wrong password, and the timing would tell which
     * accounts exist. So does an account waiting for its first password,
     * which Symfony refuses without hashing anything.
     */
    private function loadUser(string $identifier, string $password): UserInterface
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier($identifier);
        } catch (UserNotFoundException) {
            $this->spendPasswordCheck($password);

            // Without the identifier: the firewall logs this exception, and
            // users type their password in the identifier field now and then.
            // The journal keeps a fingerprint of it instead.
            throw new UserNotFoundException();
        }

        if ($user instanceof PasswordAuthenticatedUserInterface && $user->getPassword() === null) {
            $this->spendPasswordCheck($password);
        }

        return $user;
    }

    private function spendPasswordCheck(string $password): void
    {
        if ($hasher = $this->getTimingShieldHasher()) {
            $this->timingShieldHash ??= $hasher->hash('wexample-user-timing-shield');
            $hasher->verify($this->timingShieldHash, $password);
        }
    }

    /**
     * The hasher of the users, configured on the interface as the Symfony
     * recipe does; an application hashing per class only gets no shield.
     */
    private function getTimingShieldHasher(): ?PasswordHasherInterface
    {
        try {
            return $this->passwordHasherFactory->getPasswordHasher(PasswordAuthenticatedUserInterface::class);
        } catch (\RuntimeException) {
            return null;
        }
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        $session = $request->getSession();

        // The password was right, the second factor is still to come: the
        // target path waits in session for the code to be checked.
        if ($token instanceof TwoFactorTokenInterface) {
            $url = $this->urlGenerator->generate(TwoFactorController::ROUTE_FORM);
        } else {
            $url = $this->postLoginTarget->getTargetUrl($request, $token, $firewallName);
        }

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
