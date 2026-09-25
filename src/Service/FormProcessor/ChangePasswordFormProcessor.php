<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\SetPasswordForm;
use Wexample\SymfonyUser\Service\PasswordUpdaterService;

class ChangePasswordFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly PasswordUpdaterService $passwordUpdater,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function onValid(FormInterface $form)
    {
        $user = $this->security->getUser();

        if (! $user instanceof AbstractUser) {
            return;
        }

        $this->passwordUpdater->update(
            $user,
            (string) $form->get(SetPasswordForm::FIELD_NEW_PASSWORD)->getData()
        );

        $this->setNotification('@form::success.message');
    }
}
