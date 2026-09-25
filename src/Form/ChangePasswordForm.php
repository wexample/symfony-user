<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\Type\PasswordInputType;

/**
 * The signed-in user changing their own password.
 */
class ChangePasswordForm extends SetPasswordForm
{
    public const string FIELD_CURRENT_PASSWORD = 'current_password';

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(
            self::FIELD_CURRENT_PASSWORD,
            PasswordInputType::class,
            [
                self::FIELD_OPTION_NAME_LABEL => true,
                'constraints' => [new NotBlank(), new UserPassword(message: '@form::error.current_password')],
                'attr' => ['autocomplete' => 'current-password'],
            ]
        );

        parent::buildForm($builder, $options);
    }
}
