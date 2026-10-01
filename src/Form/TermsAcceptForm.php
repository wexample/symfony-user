<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Wexample\SymfonyForms\Form\AbstractForm;

/**
 * Carries the version read, so that a page left open while a new version was
 * published accepts nothing.
 */
class TermsAcceptForm extends AbstractForm
{
    public const string FIELD_VERSION = 'version';

    public static bool $ajax = true;

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder->add(self::FIELD_VERSION, HiddenType::class);

        $this->builderAddSubmit($builder);
    }
}
