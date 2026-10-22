<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyUser\Traits\AccountRolesFormTrait;

/**
 * The roles of an account an administrator already opened. The address is
 * not here: it is the identifier the account signs in with, and changing it
 * is an address to prove again.
 */
class AccountRolesForm extends AbstractForm
{
    use AccountRolesFormTrait;

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
        $this->builderAddRoles($builder, $options);
        $this->builderAddSubmit($builder);
    }
}
