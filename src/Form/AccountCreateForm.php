<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\EmailInputType;
use Wexample\SymfonyUser\Traits\AccountRolesFormTrait;

/**
 * An account an administrator opens: an address, and the roles it holds. No
 * password is asked — the account is written without one, and its holder
 * chooses it from the activation mail it is sent.
 */
class AccountCreateForm extends AbstractForm
{
    use AccountRolesFormTrait;

    public const string FIELD_EMAIL = 'email';

    public static bool $ajax = true;

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $this->configureRolesOption($resolver);
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(self::FIELD_EMAIL, EmailInputType::class, [
            self::FIELD_OPTION_NAME_LABEL => true,
            'constraints' => [new NotBlank(), new Email(), new Length(max: 180)],
        ]);

        $this->builderAddRoles($builder, $options);
        $this->builderAddSubmit($builder);
    }
}
