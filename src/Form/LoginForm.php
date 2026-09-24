<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\PasswordInputType;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * Never processed as a form: LoginFormAuthenticator intercepts its
 * submission, so the fields carry no validation constraint.
 */
class LoginForm extends AbstractForm
{
    public const string FIELD_IDENTIFIER = 'identifier';
    public const string FIELD_PASSWORD = 'password';
    public const string FIELD_REMEMBER_ME = 'remember_me';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                self::FIELD_IDENTIFIER,
                TextInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    'attr' => ['autocomplete' => 'username'],
                ]
            )
            ->add(
                self::FIELD_PASSWORD,
                PasswordInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    'attr' => ['autocomplete' => 'current-password'],
                ]
            )
            ->add(
                self::FIELD_REMEMBER_ME,
                SwitchInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                ]
            );

        $this->builderAddSubmit($builder);
    }
}
