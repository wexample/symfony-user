<?php

namespace Wexample\SymfonyUser\Security\Handler;

use Scheb\TwoFactorBundle\Security\Authentication\Exception\InvalidTwoFactorCodeException;
use Scheb\TwoFactorBundle\Security\Http\Authentication\AuthenticationRequiredHandlerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\TooManyLoginAttemptsAuthenticationException;
use Symfony\Component\Security\Http\Authentication\AuthenticationFailureHandlerInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyForms\Service\FormProcessor\FormResponsePayloadBuilder;
use Wexample\SymfonyHelpers\Helper\RequestHelper;
use Wexample\SymfonyUser\Controller\Pages\TwoFactorController;
use Wexample\SymfonyUser\Enum\TwoFactorCodeFailure;
use Wexample\SymfonyUser\Service\FormProcessor\TwoFactorCodeFormProcessor;
use Wexample\SymfonyUser\Service\TwoFactorCodeService;

/**
 * The success, failure and "code required" handlers of the `two_factor` key
 * of the firewall. Like the login, a JSON caller gets the payload every ajax
 * form reads, a page gets redirected.
 */
class TwoFactorResponseHandler implements
    AuthenticationSuccessHandlerInterface,
    AuthenticationFailureHandlerInterface,
    AuthenticationRequiredHandlerInterface
{
    use TargetPathTrait;

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly TwoFactorCodeFormProcessor $formProcessor,
        private readonly FormResponsePayloadBuilder $payloadBuilder,
        private readonly TwoFactorCodeService $codeService,
    ) {
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): Response
    {
        $session = $request->getSession();
        $firewallName = method_exists($token, 'getFirewallName') ? $token->getFirewallName() : 'main';
        $url = $this->getTargetPath($session, $firewallName) ?: $request->getBasePath() . '/';
        $this->removeTargetPath($session, $firewallName);

        if (! RequestHelper::isJsonRequest($request)) {
            return new RedirectResponse($url);
        }

        $this->formProcessor->redirect($url);

        return new JsonResponse(
            $this->payloadBuilder->build($this->formProcessor, $this->formProcessor->createForm())
        );
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        if (! RequestHelper::isJsonRequest($request)) {
            return new RedirectResponse(
                $this->urlGenerator->generate(TwoFactorController::ROUTE_FORM, ['failed' => 1])
            );
        }

        $form = $this->formProcessor->createForm();
        $this->formProcessor->addFailure($form, $this->getFailure($exception));

        return new JsonResponse($this->payloadBuilder->build($this->formProcessor, $form));
    }

    /**
     * The login throttling of the firewall counts the codes too, and refuses
     * them before the code service is even asked.
     */
    private function getFailure(AuthenticationException $exception): TwoFactorCodeFailure
    {
        return match (true) {
            $exception instanceof TooManyLoginAttemptsAuthenticationException => TwoFactorCodeFailure::TOO_MANY_ATTEMPTS,
            $exception instanceof InvalidTwoFactorCodeException => $this->codeService->getLastFailure(),
            default => TwoFactorCodeFailure::INVALID,
        };
    }

    public function onAuthenticationRequired(Request $request, TokenInterface $token): Response
    {
        $url = $this->urlGenerator->generate(TwoFactorController::ROUTE_FORM);

        if (! RequestHelper::isJsonRequest($request)) {
            return new RedirectResponse($url);
        }

        return new JsonResponse(
            ['ok' => false, 'action' => ['type' => AbstractFormProcessor::ACTION_REDIRECT, 'url' => $url]],
            Response::HTTP_UNAUTHORIZED
        );
    }
}
