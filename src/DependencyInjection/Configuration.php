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
            ->end();

        return $treeBuilder;
    }
}
