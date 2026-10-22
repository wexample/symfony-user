<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\OtpInputType;

/**
 * The first code of the authenticator app, which proves it holds the secret.
 */
class TotpEnableForm extends AbstractForm
{
    public const string FIELD_CODE = 'code';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(self::FIELD_CODE, OtpInputType::class, [self::FIELD_OPTION_NAME_LABEL => true]);

        $this->builderAddSubmit($builder);
    }
}
