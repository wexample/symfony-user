<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Controller\Pages\TotpController;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Service\TotpService;

class TotpDisableFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly TotpService $totpService,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function onValid(FormInterface $form)
    {
        $user = $this->security->getUser();

        if ($user instanceof AbstractUser) {
            $this->totpService->disable($user);
        }

        $this->redirectToRoute(TotpController::ROUTE_INDEX);
    }
}
