<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;

/**
 * Carries the version read, so that a page left open while a new version was
 * published accepts nothing.
 */
class TermsAcceptForm extends AbstractForm
{
    public const string FIELD_VERSION = 'version';

    /**
     * Said in so many words rather than read into a button pressed: the
     * consent is what was ticked.
     */
    public const string FIELD_ACCEPTED = 'accepted';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(self::FIELD_VERSION, HiddenType::class)
            ->add(self::FIELD_ACCEPTED, SwitchInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => true,
                self::FIELD_OPTION_NAME_REQUIRED => true,
                'constraints' => [new IsTrue()],
            ]);

        $this->builderAddSubmit($builder);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        parent::configureOptions($resolver);

        // Nothing to send before it is ticked.
        $resolver->setDefault('submit_when_valid', true);
    }
}
