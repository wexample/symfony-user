<?php

namespace Wexample\SymfonyUser\Service\FormProcessor;

use Doctrine\ORM\EntityManagerInterface;
use LogicException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Wexample\SymfonyForms\Service\FormProcessor\AbstractFormProcessor;
use Wexample\SymfonyTranslations\Service\LocaleService;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Form\ProfileForm;

/**
 * The signed-in account changing its own name and language. It fills the
 * form from the account and offers only the fields the application's user
 * class actually has: a class without UserWithNameTrait is offered no name.
 */
class ProfileFormProcessor extends AbstractFormProcessor
{
    public function __construct(
        FormFactoryInterface $formFactory,
        RequestStack $requestStack,
        UrlGeneratorInterface $urlGenerator,
        private readonly Security $security,
        private readonly EntityManagerInterface $entityManager,
        private readonly LocaleService $localeService,
    ) {
        parent::__construct($formFactory, $requestStack, $urlGenerator);
    }

    /**
     * Whether there is anything to change at all: a user class with no name,
     * in an application speaking one language, leaves an empty form — the
     * page then leaves the card out rather than drawing a lone submit button.
     */
    public function hasFields(): bool
    {
        return $this->isNamed() || $this->getLocaleChoices() !== [];
    }

    public function createForm(
        $data = null,
        array $options = []
    ): FormInterface {
        $user = $this->getUser();
        $named = $this->isNamed();

        $filled = [ProfileForm::FIELD_LOCALE => $user->getLocale()];

        if ($named) {
            $filled[ProfileForm::FIELD_FIRST_NAME] = $user->getFirstName();
            $filled[ProfileForm::FIELD_LAST_NAME] = $user->getLastName();
        }

        return parent::createForm(
            $data ?? $filled,
            $options + [
                ProfileForm::OPTION_NAMED => $named,
                ProfileForm::OPTION_LOCALES => $this->getLocaleChoices(),
            ]
        );
    }

    public function onValid(FormInterface $form)
    {
        $user = $this->getUser();

        if ($this->isNamed()) {
            $user
                ->setFirstName($form->get(ProfileForm::FIELD_FIRST_NAME)->getData())
                ->setLastName($form->get(ProfileForm::FIELD_LAST_NAME)->getData());
        }

        if ($form->has(ProfileForm::FIELD_LOCALE)) {
            $user->setLocale($form->get(ProfileForm::FIELD_LOCALE)->getData());
        }

        $this->entityManager->flush();

        $this->setNotification('@form::success.message');
    }

    /**
     * Language name => locale, each written in its own language: a reader
     * looking for theirs finds it as they write it. The languages the
     * application offers, `framework.enabled_locales` and the default one;
     * empty for an application speaking a single one, which is nothing to
     * choose.
     *
     * @return array<string, string>
     */
    private function getLocaleChoices(): array
    {
        $locales = $this->localeService->getLocales();

        if (count($locales) < 2) {
            return [];
        }

        $choices = [];
        foreach ($locales as $locale) {
            $choices[$this->localeService->getLocaleName($locale)] = $locale;
        }

        return $choices;
    }

    /**
     * Read on the object rather than on the trait, as the rest of the package
     * does: an application may hold the names in a class of its own.
     */
    private function isNamed(): bool
    {
        return method_exists($this->getUser(), 'getFirstName');
    }

    private function getUser(): AbstractUser
    {
        $user = $this->security->getUser();

        if (! $user instanceof AbstractUser) {
            throw new LogicException('The profile form is the signed-in account\'s own: nobody else submits it.');
        }

        return $user;
    }
}
