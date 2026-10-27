<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
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

    public const string TRUST_DEVICE_ALWAYS = 'always';
    public const string TRUST_DEVICE_ASK = 'ask';

    /**
     * Only where scheb keeps trusted devices: elsewhere a device could not be
     * remembered. Then `two_factor.trust_device` says whether the user is
     * asked, by a switch, or the device is trusted once the code is right.
     */
    public function __construct(
        #[Autowire(param: 'scheb_two_factor.trusted_device.enabled')]
        private readonly bool $trustedDevice = true,
        #[Autowire(param: 'wexample_symfony_user.two_factor.trust_device')]
        private readonly string $trustDevice = self::TRUST_DEVICE_ALWAYS,
    ) {
    }

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
            );

        if ($this->trustedDevice && $this->trustDevice === self::TRUST_DEVICE_ALWAYS) {
            $builder->add(self::FIELD_TRUSTED, HiddenType::class, ['data' => '1']);
        } elseif ($this->trustedDevice) {
            $builder->add(
                self::FIELD_TRUSTED,
                SwitchInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                    'data' => true,
                ]
            );
        }

        $this->builderAddSubmit($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        $resolver->setDefault(self::OPTION_BACKUP_CODE, false);
        $resolver->setAllowedTypes(self::OPTION_BACKUP_CODE, 'bool');
    }
}
