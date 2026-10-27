<?php

namespace Wexample\SymfonyUser\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyActivity\WexampleSymfonyActivityBundle;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyUser\DependencyInjection\Compiler\DefaultUserMappingPass;
use Wexample\SymfonyUser\Entity\AbstractUser;
use Wexample\SymfonyUser\Entity\User;
use Wexample\SymfonyUser\EventSubscriber\SecurityActivitySubscriber;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;
use Wexample\SymfonyUser\Interface\AccountGateInterface;
use Wexample\SymfonyUser\Interface\MembershipManagerInterface;

class WexampleSymfonyUserExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter(DefaultUserMappingPass::PARAMETER_USER_CLASS, $config['user_class'] ?? User::class);
        $container->setParameter('wexample_symfony_user.password_reset', $config['password_reset']);
        $container->setParameter('wexample_symfony_user.reveal_account_status', $config['reveal_account_status']);
        $container->setParameter('wexample_symfony_user.remember_me', $config['remember_me']);
        $container->setParameter('wexample_symfony_user.magic_link_login', $config['magic_link_login']);
        $container->setParameter('wexample_symfony_user.remember_locale', $config['remember_locale']);
        $container->setParameter('wexample_symfony_user.post_login.routes', $config['post_login']['routes']);
        $container->setParameter('wexample_symfony_user.post_login.default_route', $config['post_login']['default_route']);
        $container->setParameter('wexample_symfony_user.impersonation.targets', $config['impersonation']['targets']);
        $container->setParameter('wexample_symfony_user.impersonation.list_threshold', $config['impersonation']['list_threshold']);
        $container->setParameter('wexample_symfony_user.impersonation.require_intent', $config['impersonation']['require_intent']);
        $container->setParameter('wexample_symfony_user.session.idle_lifetime', $config['session']['idle_lifetime']);
        $container->setParameter('wexample_symfony_user.password.refuse_leaked', $config['password']['refuse_leaked']);
        $container->setParameter('wexample_symfony_user.activation.link_lifetime', $config['activation']['link_lifetime']);
        $container->setParameter('wexample_symfony_user.request_limit.per_identifier', $config['request_limit']['per_identifier']);
        $container->setParameter('wexample_symfony_user.request_limit.per_ip', $config['request_limit']['per_ip']);
        $container->setParameter('wexample_symfony_user.administration.page_role', $config['administration']['page_role']);
        $container->setParameter('wexample_symfony_user.administration.protected_roles', $config['administration']['protected_roles']);
        $container->setParameter('wexample_symfony_user.administration.manages', $config['administration']['manages']);
        $container->setParameter('wexample_symfony_user.administration.role_email_domains', $config['administration']['role_email_domains']);
        $container->setParameter('wexample_symfony_user.administration.exclusive_roles', $config['administration']['exclusive_roles']);
        $container->setParameter('wexample_symfony_user.terms.version', $config['terms']['version']);
        $container->setParameter('wexample_symfony_user.terms.text_route', $config['terms']['text_route']);
        $container->setParameter('wexample_symfony_user.terms.text_template', $config['terms']['text_template']);
        $container->setParameter('wexample_symfony_user.memberships.grants', $config['memberships']['grants']);
        $container->setParameter('wexample_symfony_user.memberships.roles', $config['memberships']['roles']);
        $container->setParameter('wexample_symfony_user.memberships.administer_grant', $config['memberships']['administer_grant']);
        $container->setParameter('wexample_symfony_user.two_factor.trust_device', $config['two_factor']['trust_device']);
        $container->setParameter('wexample_symfony_user.two_factor.required', $config['two_factor']['required']);
        $container->setParameter('wexample_symfony_user.two_factor.pending_lifetime', $config['two_factor']['pending_lifetime']);
        $container->setParameter('wexample_symfony_user.two_factor.app_required_roles', $config['two_factor']['app_required_roles']);

        $container
            ->registerForAutoconfiguration(AccountGateInterface::class)
            ->addTag(AccountGateInterface::TAG);
        $container
            ->registerForAutoconfiguration(AccountAdministrationGuardInterface::class)
            ->addTag(AccountAdministrationGuardInterface::TAG);
        $container
            ->registerForAutoconfiguration(MembershipManagerInterface::class)
            ->addTag(MembershipManagerInterface::TAG);

        $this->loadConfig(
            __DIR__,
            $container
        );

        // The history of the accounts, where the application enabled
        // symfony-activity is always installed, but an application may leave its
        // bundle out — and then it has no services to record into.
        if (in_array(WexampleSymfonyActivityBundle::class, $container->getParameter('kernel.bundles'), true)) {
            $container->autowire(SecurityActivitySubscriber::class)->setAutoconfigured(true);
        }
    }

    /**
     * The user class the firewall's entity provider names, when it extends
     * AbstractUser: the application said it there already. Read here, where
     * the whole configuration is at hand — load() only sees this extension's
     * —, and prepended, so a `user_class` set by the application wins.
     */
    public function prepend(ContainerBuilder $container): void
    {
        parent::prepend($container);

        if ($class = $this->getProviderUserClass($container)) {
            $container->prependExtensionConfig($this->getAlias(), ['user_class' => $class]);
        }
    }

    private function getProviderUserClass(ContainerBuilder $container): ?string
    {
        foreach ($container->getExtensionConfig('security') as $config) {
            foreach ($config['providers'] ?? [] as $provider) {
                $class = $provider['entity']['class'] ?? null;

                if (is_string($class) && is_subclass_of($class, AbstractUser::class)) {
                    return $class;
                }
            }
        }

        return null;
    }
}
