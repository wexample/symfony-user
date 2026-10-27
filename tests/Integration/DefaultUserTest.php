<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyUser\DependencyInjection\Compiler\DefaultUserMappingPass;
use Wexample\SymfonyUser\Entity\User;
use Wexample\SymfonyUser\Service\AccountDirectoryService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User as FixtureUser;

/**
 * The fixture application brings its own user class, named by its security
 * provider: the package's User is left out of the mapping, so the two never
 * share the `user` table.
 */
class DefaultUserTest extends KernelTestCase
{
    public function testThePackagesUserStepsAsideForTheApplicationsClass(): void
    {
        self::bootKernel();
        $container = self::getContainer();

        $this->assertSame(FixtureUser::class, $container->getParameter(DefaultUserMappingPass::PARAMETER_USER_CLASS));
        $this->assertSame(FixtureUser::class, $container->get(AccountDirectoryService::class)->getUserClass());

        $mapped = array_map(
            static fn ($metadata) => $metadata->getName(),
            $container->get('doctrine')->getManager()->getMetadataFactory()->getAllMetadata()
        );
        $this->assertContains(FixtureUser::class, $mapped);
        $this->assertNotContains(User::class, $mapped);
    }
}
