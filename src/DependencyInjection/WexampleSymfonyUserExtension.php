<?php

namespace Wexample\SymfonyUser\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyUser\Interface\AccountGateInterface;
use Wexample\SymfonyHelpers\DependencyInjection\AbstractWexampleSymfonyExtension;

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
        $container->setParameter('wexample_symfony_user.terms.version', $config['terms']['version']);
        $container->setParameter('wexample_symfony_user.terms.text_route', $config['terms']['text_route']);
        $container->setParameter('wexample_symfony_user.two_factor.required', $config['two_factor']['required']);
        $container->setParameter('wexample_symfony_user.two_factor.pending_lifetime', $config['two_factor']['pending_lifetime']);
        $container->setParameter('wexample_symfony_user.two_factor.app_required_roles', $config['two_factor']['app_required_roles']);

        $container
            ->registerForAutoconfiguration(AccountGateInterface::class)
            ->addTag(AccountGateInterface::TAG);

        $this->loadConfig(
            __DIR__,
            $container
        );
    }
}
