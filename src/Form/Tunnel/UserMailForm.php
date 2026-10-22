<?php

namespace Wexample\SymfonyUser\Form\Tunnel;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\EmailInputType;

/**
 * No submit button: in a tunnel, the way on is the next button of the step.
 */
class UserMailForm extends AbstractForm
{
    public const string FIELD_EMAIL = 'email';

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(
            self::FIELD_EMAIL,
            EmailInputType::class,
            [
                self::FIELD_OPTION_NAME_LABEL => true,
                'constraints' => [new NotBlank(), new Email()],
                'attr' => ['autocomplete' => 'email'],
            ]
        );
    }
}
