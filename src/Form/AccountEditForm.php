<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\EmailInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;
use Wexample\SymfonyUser\Entity\AbstractUser;

/**
 * What an account an administrator already opened is called: its address,
 * its username, and its name where the application's user class holds one
 * (`named`). Its roles have a form of their own, AccountRolesForm.
 */
class AccountEditForm extends AbstractForm
{
    public const string FIELD_EMAIL = 'email';
    public const string FIELD_USERNAME = 'username';
    public const string FIELD_FIRST_NAME = ProfileForm::FIELD_FIRST_NAME;
    public const string FIELD_LAST_NAME = ProfileForm::FIELD_LAST_NAME;

    public const string OPTION_NAMED = 'named';

    public static bool $ajax = true;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_NAMED, false);
        $resolver->setAllowedTypes(self::OPTION_NAMED, 'bool');
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(self::FIELD_EMAIL, EmailInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
        ]);

        $builder->add(self::FIELD_USERNAME, TextInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            self::FIELD_OPTION_NAME_REQUIRED => false,
            'constraints' => [new Regex(pattern: AbstractUser::USERNAME_PATTERN, message: '@form::error.username_pattern')],
        ]);

        if ($options[self::OPTION_NAMED]) {
            foreach ([self::FIELD_FIRST_NAME, self::FIELD_LAST_NAME] as $field) {
                $builder->add($field, TextInputType::class, [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                    'constraints' => [new Length(max: 100)],
                ]);
            }
        }

        $this->builderAddSubmit($builder);
    }
}
