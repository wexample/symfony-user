<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\SelectInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * The account to impersonate, by its identifier: a select of the accounts
 * shown (option `targets`, label => identifier), a text field otherwise —
 * which is how the submission reads it, ImpersonateFormProcessor checking
 * the account again.
 */
class ImpersonateForm extends AbstractForm
{
    public const string FIELD_ACCOUNT = 'account';
    public const string OPTION_TARGETS = 'targets';

    /** While impersonating, the way back, drawn beside the submit button. */
    public const string OPTION_EXIT_URL = 'exit_url';

    public static bool $ajax = true;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_TARGETS, null);
        $resolver->setAllowedTypes(self::OPTION_TARGETS, ['null', 'array']);
        $resolver->setDefault(self::OPTION_EXIT_URL, null);
        $resolver->setAllowedTypes(self::OPTION_EXIT_URL, ['null', 'string']);
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        parent::buildView($view, $form, $options);

        $view->vars[self::OPTION_EXIT_URL] = $options[self::OPTION_EXIT_URL];
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $targets = $options[self::OPTION_TARGETS];

        $builder->add(
            self::FIELD_ACCOUNT,
            $targets === null ? TextInputType::class : SelectInputType::class,
            $targets === null ? [] : [
                'choices' => $targets,
                'auto_translate_choices' => false,
                'choice_translation_domain' => false,
            ]
        );

        $this->builderAddSubmit($builder);
    }
}
