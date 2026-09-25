<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Wexample\SymfonyUser\Service\AssignableRolesService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class AssignableRolesServiceTest extends TestCase
{
    public function testAnEditorCannotGrantARoleAboveTheirOwn(): void
    {
        $service = new AssignableRolesService(new RoleHierarchy([
            'ROLE_ADMIN' => ['ROLE_MANAGER'],
            'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        ]));
        $admin = (new User())->setRoles(['ROLE_ADMIN']);

        $this->assertEqualsCanonicalizing(
            ['ROLE_ADMIN', 'ROLE_MANAGER', 'ROLE_USER'],
            $service->getAssignableRoles($admin)
        );
        $this->assertTrue($service->canAssign($admin, ['ROLE_MANAGER']));
        $this->assertFalse($service->canAssign($admin, ['ROLE_MANAGER', 'ROLE_SUPER_ADMIN']));
    }
}
