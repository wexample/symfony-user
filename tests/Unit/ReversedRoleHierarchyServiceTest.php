<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyUser\Service\ReversedRoleHierarchyService;

class ReversedRoleHierarchyServiceTest extends TestCase
{
    /**
     * The hierarchy of network, the application this package comes from.
     */
    private const array HIERARCHY = [
        'ROLE_CLIENT' => ['ROLE_USER'],
        'ROLE_WORKER' => ['ROLE_USER'],
        'ROLE_MANAGER' => ['ROLE_WORKER'],
        'ROLE_MANDATORY' => ['ROLE_MANAGER'],
        'ROLE_TREASURER' => ['ROLE_MANDATORY'],
        'ROLE_ADMIN' => ['ROLE_TREASURER'],
        'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
    ];

    public function testParentRolesAreEveryRoleAtOrAbove(): void
    {
        $service = new ReversedRoleHierarchyService(self::HIERARCHY);

        $this->assertEqualsCanonicalizing(
            ['ROLE_WORKER', 'ROLE_MANAGER', 'ROLE_MANDATORY', 'ROLE_TREASURER', 'ROLE_ADMIN', 'ROLE_SUPER_ADMIN'],
            $service->getParentRoles('ROLE_WORKER')
        );
        $this->assertSame(['ROLE_SUPER_ADMIN'], $service->getParentRoles('ROLE_SUPER_ADMIN'));
        $this->assertNotContains('ROLE_CLIENT', $service->getParentRoles('ROLE_WORKER'));
        $this->assertContains('ROLE_CLIENT', $service->getParentRoles('ROLE_USER'));
    }
}
