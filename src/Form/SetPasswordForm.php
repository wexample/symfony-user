<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotCompromisedPassword;
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

    public function __construct(
        #[Autowire(param: 'wexample_symfony_user.password.refuse_leaked')]
        private readonly bool $refuseLeaked = false,
    ) {
    }

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
                'constraints' => $this->passwordConstraints(),
            ]
        );

        $this->builderAddSubmit($builder);
    }

    /**
     * How strong the password is, measured here. Whether it has leaked
     * already is a second question, and asking it costs a call to an API —
     * hence `password.refuse_leaked`, off until an application asks for it.
     * skipOnError, so that an API answering badly refuses nobody a password.
     *
     * @return list<Constraint>
     */
    private function passwordConstraints(): array
    {
        $constraints = [
            new NotBlank(),
            new Length(min: 8, max: 4096),
            new PasswordStrength(minScore: PasswordStrength::STRENGTH_MEDIUM),
        ];

        if ($this->refuseLeaked) {
            $constraints[] = new NotCompromisedPassword(skipOnError: true);
        }

        return $constraints;
    }
}
