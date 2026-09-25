<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\TextInputType;

class PasswordResetRequestForm extends AbstractForm
{
    public const string FIELD_IDENTIFIER = 'identifier';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(
            self::FIELD_IDENTIFIER,
            TextInputType::class,
            [
                self::FIELD_OPTION_NAME_LABEL => true,
                'constraints' => [new NotBlank()],
                'attr' => ['autocomplete' => 'username'],
            ]
        );

        $this->builderAddSubmit($builder);
    }
}
