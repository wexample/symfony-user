<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyUser\DependencyInjection\Compiler\DefaultUserMappingPass;
use Wexample\SymfonyUser\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\PackageUserAppKernel;

/**
 * `user_class` set to the package's own User: it is mapped, whatever class
 * the security provider names — the explicit option wins. (The fixture
 * keeps its own user class mapped too: no schema is built here.)
 */
class PackageUserTest extends KernelTestCase
{
    protected static function getKernelClass(): string
    {
        return PackageUserAppKernel::class;
    }

    public function testThePackagesUserIsMappedWhenAskedFor(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertSame(User::class, $container->getParameter(DefaultUserMappingPass::PARAMETER_USER_CLASS));

        $mapped = array_map(
            static fn ($metadata) => $metadata->getName(),
            $container->get('doctrine')->getManager()->getMetadataFactory()->getAllMetadata()
        );
        $this->assertContains(User::class, $mapped);
    }
}
