<?php

namespace Wexample\SymfonyUser\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Wexample\SymfonyUser\Entity\User;

/**
 * Leaves the package's own User out of the Doctrine mapping where the
 * application brings its own class: both map the `user` table, and Doctrine
 * would otherwise want them as a parent and a child.
 */
class DefaultUserMappingPass implements CompilerPassInterface
{
    public const string PARAMETER_USER_CLASS = 'wexample_symfony_user.user_class';

    public function process(ContainerBuilder $container): void
    {
        if ($container->getParameter(self::PARAMETER_USER_CLASS) === User::class) {
            return;
        }

        $file = (new \ReflectionClass(User::class))->getFileName();

        foreach ($container->getDefinitions() as $id => $definition) {
            if (str_ends_with($id, '_attribute_metadata_driver')) {
                $definition->addMethodCall('addExcludePaths', [[$file]]);
            }
        }
    }
}
