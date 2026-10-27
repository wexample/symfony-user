<?php

namespace Wexample\SymfonyUser\Form;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Wexample\SymfonyForms\Form\AbstractForm;
use Wexample\SymfonyForms\Form\Type\PasswordInputType;
use Wexample\SymfonyForms\Form\Type\SwitchInputType;
use Wexample\SymfonyForms\Form\Type\TextInputType;

/**
 * Never processed as a form: LoginFormAuthenticator intercepts its
 * submission, so the fields carry no validation constraint.
 */
class LoginForm extends AbstractForm
{
    public const string FIELD_IDENTIFIER = 'identifier';
    public const string FIELD_PASSWORD = 'password';
    public const string FIELD_REMEMBER_ME = 'remember_me';

    public static bool $ajax = true;

    /**
     * The firewall listener a "stay signed in" switch needs: without it, the
     * switch would be drawn and do nothing.
     */
    private const string FIREWALL_REMEMBER_ME = 'remember_me';

    public function __construct(
        private readonly Security $security,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'wexample_symfony_user.remember_me')]
        private readonly bool $rememberMe = false,
    ) {
    }

    public function buildForm(
        FormBuilderInterface $builder,
        array $options
    ): void {
        $builder
            ->add(
                self::FIELD_IDENTIFIER,
                TextInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    'attr' => ['autocomplete' => 'username'],
                ]
            )
            ->add(
                self::FIELD_PASSWORD,
                PasswordInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    'attr' => ['autocomplete' => 'current-password'],
                ]
            );

        if ($this->rememberMe && $this->firewallRemembers()) {
            $builder->add(
                self::FIELD_REMEMBER_ME,
                SwitchInputType::class,
                [
                    self::FIELD_OPTION_NAME_LABEL => true,
                    self::FIELD_OPTION_NAME_REQUIRED => false,
                ]
            );
        }

        $this->builderAddSubmit($builder);
    }

    private function firewallRemembers(): bool
    {
        $request = $this->requestStack->getMainRequest();

        return $request !== null
            && in_array(self::FIREWALL_REMEMBER_ME, $this->security->getFirewallConfig($request)?->getAuthenticators() ?? [], true);
    }
}
