<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\MagicLinkRequestForm;
use Wexample\SymfonyUser\Service\MagicLinkService;

/**
 * Answers the same whether the account exists or not, so the form never
 * tells which accounts exist.
 */
class MagicLinkRequestFormProcessor extends AbstractFormProcessor
{
    public const string MESSAGE_SENT = '@form::success.message';

    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly UserProviderInterface $userProvider,
        private readonly MagicLinkService $magicLinkService,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    public function getRequiredRoles(): array
    {
        return [RoleHelper::PUBLIC_ACCESS];
    }

    public function onValid(FormInterface $form)
    {
        try {
            $user = $this->userProvider->loadUserByIdentifier(
                (string) $form->get(MagicLinkRequestForm::FIELD_IDENTIFIER)->getData()
            );
        } catch (UserNotFoundException) {
            $user = null;
        }

        if ($user instanceof AbstractUser) {
            $this->magicLinkService->sendLink($user);
        }

        $this->setNotification(self::MESSAGE_SENT);
    }
}
