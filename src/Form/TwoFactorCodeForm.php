<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\OtpInputType;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * Never processed as a form: scheb/2fa-bundle checks the code, its firewall
 * `check_path` pointing at the submission of this form.
 */
class TwoFactorCodeForm extends AbstractForm
{
    public const string FIELD_CODE = 'code';
    public const string FIELD_TRUSTED = 'trusted';

    /**
     * A backup code is longer than a code and holds letters: the same form,
     * same name and same submission, with a plain text field.
     */
    public const string OPTION_BACKUP_CODE = 'backup_code';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                self::FIELD_CODE,
                $options[self::OPTION_BACKUP_CODE] ? TextInputType::class : OtpInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => $options[self::OPTION_BACKUP_CODE] ? 'field.code.backup_label' : true,
                    'attr' => ['autocomplete' => 'one-time-code'],
                ]
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

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_BACKUP_CODE, false);
        $resolver->setAllowedTypes(self::OPTION_BACKUP_CODE, 'bool');
    }
}
