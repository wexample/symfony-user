<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\PasswordStrength;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\PasswordInputType;

/**
 * A new password typed twice, with no current password asked: for an
 * administrator setting it, or a reset link proving who the user is.
 */
class SetPasswordForm extends AbstractForm
{
    public const string FIELD_NEW_PASSWORD = 'new_password';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(
            self::FIELD_NEW_PASSWORD,
            RepeatedType::class,
            [
                'type' => PasswordInputType::class,
                'invalid_message' => '@form::error.mismatch',
                'first_options' => [
                    self::FIELD_OPTION_NAME_LABEL => 'field.new_password.first.label',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    self::FIELD_OPTION_NAME_LABEL => 'field.new_password.second.label',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'constraints' => [
                    new NotBlank(),
                    new Length(min: 8, max: 4096),
                    new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM),
                ],
            ]
        );

        $this->builderAddSubmit($builder);
    }
}
