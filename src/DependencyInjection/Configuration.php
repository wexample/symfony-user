<?php

namespace Wexample\SymfonyUser\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Wexample\SymfonyUser\Enum\PasswordResetMode;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('wexample_symfony_user');

        $treeBuilder->getRootNode()
            ->children()
                ->enumNode('password_reset')
                    ->info('How a user who forgot their password gets back in: a link to a reset form, or a magic link.')
                    ->values(array_map(static fn (PasswordResetMode $mode) => $mode->value, PasswordResetMode::cases()))
                    ->defaultValue(PasswordResetMode::TOKEN->value)
                ->end()
                ->booleanNode('reveal_account_status')
                    ->info('Tell a disabled or locked account why it cannot sign in, once its password is right. Off, every failure reads the same.')
                    ->defaultFalse()
                ->end()
                ->booleanNode('magic_link_login')
                    ->info('Offer to sign in by magic link on the login page. Off, MagicLinkService still serves the links an application sends itself.')
                    ->defaultTrue()
                ->end()
                ->arrayNode('terms')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('version')
                            ->info('The version of the terms of use in force. Set, every signed-in user must have accepted it before reaching anything else; changing it asks everyone again, at their next request.')
                            ->defaultNull()
                        ->end()
                        ->scalarNode('text_route')
                            ->info('The route of the page holding the text of the terms, owned by the application.')
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('two_factor')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('pending_lifetime')
                            ->info('Seconds a correct password waits for its second factor. Past it, the sign-in starts over from the password.')
                            ->defaultValue(600)
                            ->min(60)
                        ->end()
                        ->booleanNode('required')
                            ->info('Every sign-in asks a second factor — magic link and the login after a reset included —, and no account is exempt.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('app_required_roles')
                            ->info('Roles that must use an authenticator app: signed in by email code until it is set up, held on its setup page meanwhile, never allowed to turn it off.')
                            ->scalarPrototype()->end()
                            ->defaultValue([])
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
