<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Wexample\SymfonyHelpers\Helper\RoleHelper;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

class AbstractUserTest extends TestCase
{
    public function testEmailAndUsernameAreLowercased(): void
    {
        $user = (new User())
            ->setEmail('  Jane.Doe@Example.COM ')
            ->setUsername('Jane_Doe');

        $this->assertSame('jane.doe@example.com', $user->getEmail());
        $this->assertSame('jane.doe@example.com', $user->getUserIdentifier());
        $this->assertSame('jane_doe', $user->getUsername());
    }

    public function testRolesAlwaysContainRoleUserWithoutStoringIt(): void
    {
        $user = new User();
        $this->assertSame([RoleHelper::ROLE_USER], $user->getRoles());

        $user->setRoles([RoleHelper::ROLE_ADMIN, RoleHelper::ROLE_USER, RoleHelper::ROLE_ADMIN]);
        $this->assertSame([RoleHelper::ROLE_ADMIN, RoleHelper::ROLE_USER], $user->getRoles());
    }

    public function testNewUserIsDisabledUnlockedAndDated(): void
    {
        $user = new User();

        $this->assertFalse($user->isEnabled());
        $this->assertFalse($user->isLocked());
        $this->assertNotNull($user->getDateCreated());
        $this->assertNull($user->getDateLastLogin());
    }

    public function testRoleChangeKeepsTheSessionUserEqual(): void
    {
        [$stored, $fresh] = $this->createSameUserTwice();
        $fresh->setRoles([RoleHelper::ROLE_ADMIN]);

        $this->assertTrue($stored->isEqualTo($fresh));
    }

    public function testPasswordEnabledOrLockedChangeBreaksEquality(): void
    {
        [$stored, $fresh] = $this->createSameUserTwice();
        $this->assertFalse($stored->isEqualTo((clone $fresh)->setPassword('other')));
        $this->assertFalse($stored->isEqualTo((clone $fresh)->setEnabled(false)));
        $this->assertFalse($stored->isEqualTo((clone $fresh)->setLocked(true)));
        $this->assertFalse($stored->isEqualTo((new User())->setPassword('hash')->setEnabled(true)));
    }

    public function testDisplayNameFallsBackToUsernameThenEmail(): void
    {
        $user = (new User())->setEmail('jane@example.com');
        $this->assertSame('jane@example.com', $user->getDisplayName());

        $user->setUsername('jane');
        $this->assertSame('jane', $user->getDisplayName());

        $user->setFirstName('Jane')->setLastName('Doe');
        $this->assertSame('Jane Doe', $user->getDisplayName());
        $this->assertSame('Jane', $user->getDisplayName(short: true));
    }

    /**
     * @return array{User, User}
     */
    private function createSameUserTwice(): array
    {
        $stored = (new User())->setPassword('hash')->setEnabled(true);
        $fresh = (new User())->setPassword('hash')->setEnabled(true);
        $fresh->setId($stored->getId());

        return [$stored, $fresh];
    }
}
