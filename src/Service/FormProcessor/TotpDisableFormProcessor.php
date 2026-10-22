<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Routing\UserRoute;
use Wexample\SymfonyUser\Service\TotpService;
use Wexample\SymfonyUser\Service\TwoFactorPolicyService;

class TotpDisableFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly TotpService $totpService,
        private readonly TwoFactorPolicyService $policy,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    /**
     * A role that must use an app cannot turn it off, whatever the page shows.
     */
    public function formIsValid(FormInterface $form): bool
    {
        $user = $this->security->getUser();

        if ($user instanceof AbstractUser && $this->policy->requiresApp($user)) {
            $form->addError(new FormError('@form::error.required'));

            return false;
        }

        return parent::formIsValid($form);
    }

    public function onValid(FormInterface $form)
    {
        $user = $this->security->getUser();

        if ($user instanceof AbstractUser) {
            $this->totpService->disable($user);
        }

        $this->redirectToRoute(UserRoute::TOTP);
    }
}
