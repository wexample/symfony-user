<?php

namespace Wexample\SymfonyUser\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;
use Wexample\SymfonyUser\Interface\AccountAdministrationGuardInterface;
use Wexample\SymfonyUser\Interface\AccountGateInterface;

class WexampleSymfonyUserExtension extends AbstractWexampleSymfonyExtension
{
    public function load(
        array $configs,
        ContainerBuilder $container
    ): void {
        $config = $this->processConfiguration(new Configuration(), $configs);

        $container->setParameter('wexample_symfony_user.password_reset', $config['password_reset']);
        $container->setParameter('wexample_symfony_user.reveal_account_status', $config['reveal_account_status']);
        $container->setParameter('wexample_symfony_user.magic_link_login', $config['magic_link_login']);
        $container->setParameter('wexample_symfony_user.remember_locale', $config['remember_locale']);
        $container->setParameter('wexample_symfony_user.post_login.routes', $config['post_login']['routes']);
        $container->setParameter('wexample_symfony_user.post_login.default_route', $config['post_login']['default_route']);
        $container->setParameter('wexample_symfony_user.activation.link_lifetime', $config['activation']['link_lifetime']);
        $container->setParameter('wexample_symfony_user.request_limit.per_identifier', $config['request_limit']['per_identifier']);
        $container->setParameter('wexample_symfony_user.request_limit.per_ip', $config['request_limit']['per_ip']);
        $container->setParameter('wexample_symfony_user.administration.protected_roles', $config['administration']['protected_roles']);
        $container->setParameter('wexample_symfony_user.administration.manages', $config['administration']['manages']);
        $container->setParameter('wexample_symfony_user.administration.role_email_domains', $config['administration']['role_email_domains']);
        $container->setParameter('wexample_symfony_user.administration.exclusive_roles', $config['administration']['exclusive_roles']);
        $container->setParameter('wexample_symfony_user.terms.version', $config['terms']['version']);
        $container->setParameter('wexample_symfony_user.terms.text_route', $config['terms']['text_route']);
        $container->setParameter('wexample_symfony_user.terms.text_template', $config['terms']['text_template']);
        $container->setParameter('wexample_symfony_user.two_factor.required', $config['two_factor']['required']);
        $container->setParameter('wexample_symfony_user.two_factor.pending_lifetime', $config['two_factor']['pending_lifetime']);
        $container->setParameter('wexample_symfony_user.two_factor.app_required_roles', $config['two_factor']['app_required_roles']);

        $container
            ->registerForAutoconfiguration(AccountGateInterface::class)
            ->addTag(AccountGateInterface::TAG);
        $container
            ->registerForAutoconfiguration(AccountAdministrationGuardInterface::class)
            ->addTag(AccountAdministrationGuardInterface::TAG);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
