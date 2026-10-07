<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\SelectInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * What an account may change about itself, beside its password: the name it
 * is shown under, and the language its mails are written in.
 *
 * Not its email, which is the identifier it signs in with: changing that one
 * is an address to prove again, a walk the package does not have. Not its
 * roles either — those are an administrator's.
 *
 * Both halves are optional, because both depend on the application's own
 * user class: `named` only when it uses UserWithNameTrait, `locales` only
 * when more than one language is enabled. ProfileFormProcessor reads which.
 */
class ProfileForm extends AbstractForm
{
    public const string FIELD_FIRST_NAME = 'first_name';
    public const string FIELD_LAST_NAME = 'last_name';
    public const string FIELD_LOCALE = 'locale';

    public const string OPTION_NAMED = 'named';

    /** Language name => locale, or empty for an application in one language. */
    public const string OPTION_LOCALES = 'locales';

    public static bool $ajax = true;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_NAMED, false);
        $resolver->setAllowedTypes(self::OPTION_NAMED, 'bool');
        $resolver->setDefault(self::OPTION_LOCALES, []);
        $resolver->setAllowedTypes(self::OPTION_LOCALES, 'array');
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        if ($options[self::OPTION_NAMED]) {
            foreach ([self::FIELD_FIRST_NAME, self::FIELD_LAST_NAME] as $field) {
                $builder->add($field, TextInputType::class, [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                    'constraints' => [new Length(max: 100)],
                ]);
            }
        }

        if ($options[self::OPTION_LOCALES]) {
            $builder->add(self::FIELD_LOCALE, SelectInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => false,
                'choices' => $options[self::OPTION_LOCALES],
                'auto_translate_choices' => false,
                'choice_translation_domain' => false,
                // Empty: the mails of the account go out in the default
                // language, which is what an account that never chose does.
                'placeholder' => 'field.locale.placeholder',
            ]);
        }

        $this->builderAddSubmit($builder);
    }
}
