<?php

namespace Wexample\SymfonyUser\Traits;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;

/**
 * The roles of an account, one switch each: the roles the editor
 * administers, which AssignableRolesService names, and no other — the form
 * cannot offer what the service would refuse.
 *
 * A switch each rather than a multiple select, which the design system's
 * `select_input` does not draw. The field is named after the role, lowercased
 * — `ROLE_ADMIN` becomes `role_admin` —, and the label is the role itself:
 * there is no translating a name the application invented.
 */
trait AccountRolesFormTrait
{
    /** The roles the editor administers, the only ones the form offers. */
    public const string OPTION_ROLES = 'roles';

    public static function getRoleFieldName(string $role): string
    {
        return strtolower($role);
    }

    /**
     * The roles switched on, read back from a submitted form.
     *
     * @param list<string> $offered
     *
     * @return list<string>
     */
    public static function getSubmittedRoles(FormInterface $form, array $offered): array
    {
        return array_values(array_filter(
            $offered,
            static fn (string $role) => $form->get(self::getRoleFieldName($role))->getData() === true
        ));
    }

    protected function configureRolesOption(OptionsResolver $resolver): void
    {
        $resolver->setDefault(self::OPTION_ROLES, []);
        $resolver->setAllowedTypes(self::OPTION_ROLES, 'array');
    }

    /**
     * @param array<string, mixed> $options
     */
    protected function builderAddRoles(FormBuilderInterface $builder, array $options): void
    {
        foreach ($options[self::OPTION_ROLES] as $role) {
            $builder->add(self::getRoleFieldName($role), SwitchInputType::class, [
                self::FIELD_OPTION_NAME_LABEL => $role,
                self::FIELD_OPTION_NAME_REQUIRED => false,
                'translation_domain' => false,
            ]);
        }
    }
}
