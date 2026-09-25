<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\OtpInputType;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;

/**
 * Never processed as a form: scheb/2fa-bundle checks the code, its firewall
 * `check_path` pointing at the submission of this form.
 */
class TwoFactorCodeForm extends AbstractForm
{
    public const string FIELD_CODE = 'code';
    public const string FIELD_TRUSTED = 'trusted';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                self::FIELD_CODE,
                OtpInputType::class,
                [self::FIELD_OPTION_NAME_LABEL => true]
            )
            ->add(
                self::FIELD_TRUSTED,
                SwitchInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                    'data' => true,
                ]
            );

        $this->builderAddSubmit($builder);
    }
}
