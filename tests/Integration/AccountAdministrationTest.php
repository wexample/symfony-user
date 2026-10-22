<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Tests\Fixtures\App\AdministrationAppKernel;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * ROLE_MANAGER is protected, restricted to example.com, never given with
 * ROLE_CUSTOMER; ROLE_OWNER includes it, and it includes ROLE_SUPPORT and
 * ROLE_CUSTOMER.
 */
class AccountAdministrationTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private EntityManagerInterface $entityManager;

    private AccountAdministrationService $administration;

    /**
     * @var list<SecurityEvent>
     */
    private array $events = [];

    protected static function getKernelClass(): string
    {
        return AdministrationAppKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createDatabaseSchema();
        $this->administration = self::getContainer()->get('test.account_administration');

        self::getContainer()->get('event_dispatcher')->addListener(
            SecurityEvent::class,
            fn (SecurityEvent $event) => $this->events[] = $event
        );
    }

    public function testAnActorNeitherDeactivatesNorLocksNorDemotesThemselves(): void
    {
        $manager = $this->createUser('manager', ['ROLE_MANAGER']);
        $this->createUser('other-manager', ['ROLE_MANAGER']);

        $this->assertRefused(AccountAdministrationRefusal::SELF_DEACTIVATION, fn () => $this->administration->deactivate($manager, $manager));
        $this->assertRefused(AccountAdministrationRefusal::SELF_DEACTIVATION, fn () => $this->administration->lock($manager, $manager));
        $this->assertRefused(AccountAdministrationRefusal::SELF_DEMOTION, fn () => $this->administration->changeRoles($manager, $manager, []));

        $this->entityManager->refresh($manager);
        $this->assertTrue($manager->isEnabled());
        $this->assertFalse($manager->isLocked());
        $this->assertContains('ROLE_MANAGER', $manager->getRoles());
    }

    public function testTheLastActiveHolderOfAProtectedRoleIsKept(): void
    {
        $owner = $this->createUser('owner', ['ROLE_OWNER']);
        $manager = $this->createUser('manager', ['ROLE_MANAGER']);

        // Two holders, the owner through the hierarchy: one can go.
        $this->administration->deactivate($owner, $manager);
        $this->assertFalse($manager->isEnabled());

        // The owner is the last one now; a disabled holder does not count.
        $other = $this->createUser('other-owner', ['ROLE_OWNER']);
        $this->administration->lock($owner, $other);

        $this->assertRefused(AccountAdministrationRefusal::LAST_PROTECTED_ROLE_HOLDER, fn () => $this->administration->deactivate($other, $owner));
        $this->assertRefused(AccountAdministrationRefusal::LAST_PROTECTED_ROLE_HOLDER, fn () => $this->administration->lock($other, $owner));
        $this->assertRefused(AccountAdministrationRefusal::LAST_PROTECTED_ROLE_HOLDER, fn () => $this->administration->changeRoles($other, $owner, ['ROLE_SUPPORT']));

        // Back to two holders, the role can be taken away.
        $this->administration->unlock($owner, $other);
        $this->administration->changeRoles($other, $owner, ['ROLE_SUPPORT']);
        $this->assertSame(['ROLE_SUPPORT', 'ROLE_USER'], $owner->getRoles());
    }

    public function testNoAccountNorRoleAboveTheActor(): void
    {
        $manager = $this->createUser('manager', ['ROLE_MANAGER']);
        $owner = $this->createUser('owner', ['ROLE_OWNER']);
        $support = $this->createUser('support', ['ROLE_SUPPORT']);

        $this->assertRefused(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, fn () => $this->administration->deactivate($manager, $owner));
        $this->assertRefused(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, fn () => $this->administration->changeRoles($manager, $support, ['ROLE_OWNER']));

        $this->administration->changeRoles($manager, $support, ['ROLE_MANAGER']);
        $this->assertContains('ROLE_MANAGER', $support->getRoles());
    }

    public function testARestrictedRoleStaysInsideItsDomain(): void
    {
        $manager = $this->createUser('manager', ['ROLE_MANAGER']);

        foreach (['x@example.com.evil.fr', 'x@evil.fr?@example.com', 'x@sub.example.com', 'x@evil.fr'] as $email) {
            $target = $this->createUser(uniqid('target'), [], $email);

            $this->assertRefused(
                AccountAdministrationRefusal::EMAIL_DOMAIN_NOT_ALLOWED,
                fn () => $this->administration->changeRoles($manager, $target, ['ROLE_MANAGER']),
                $email
            );
        }

        $target = $this->createUser('target', [], 'Target@EXAMPLE.com');
        $this->administration->changeRoles($manager, $target, ['ROLE_MANAGER']);

        // Whoever changes the address afterwards: the account is not written.
        $target->setEmail('target@evil.fr');
        $this->assertRefused(AccountAdministrationRefusal::EMAIL_DOMAIN_NOT_ALLOWED, fn () => $this->entityManager->flush());
    }

    public function testExclusiveRolesAreNeverHeldTogether(): void
    {
        $manager = $this->createUser('manager', ['ROLE_MANAGER']);
        $customer = $this->createUser('customer', ['ROLE_CUSTOMER']);

        $exception = $this->assertRefused(
            AccountAdministrationRefusal::EXCLUSIVE_ROLES,
            fn () => $this->administration->changeRoles($manager, $customer, ['ROLE_CUSTOMER', 'ROLE_MANAGER'])
        );
        $this->assertSame(['ROLE_MANAGER', 'ROLE_CUSTOMER'], $exception->roles);

        // Whoever writes the account.
        $this->assertRefused(
            AccountAdministrationRefusal::EXCLUSIVE_ROLES,
            fn () => $this->createUser('both', ['ROLE_MANAGER', 'ROLE_CUSTOMER'])
        );

        // A manager reaches ROLE_CUSTOMER without holding it: they administer customers.
        $this->administration->changeRoles($manager, $customer, ['ROLE_SUPPORT']);
        $this->assertSame(['ROLE_SUPPORT', 'ROLE_USER'], $customer->getRoles());
    }

    public function testEveryChangeAndEveryRefusalIsJournaled(): void
    {
        $owner = $this->createUser('owner', ['ROLE_OWNER']);
        $target = $this->createUser('target', ['ROLE_SUPPORT']);
        $this->events = [];

        $this->administration->deactivate($owner, $target);
        $this->administration->reactivate($owner, $target);
        $this->administration->lock($owner, $target);
        $this->administration->unlock($owner, $target);
        $this->administration->changeRoles($owner, $target, ['ROLE_MANAGER']);
        // No change, no entry.
        $this->administration->changeRoles($owner, $target, ['ROLE_MANAGER']);
        $this->assertRefused(AccountAdministrationRefusal::SELF_DEACTIVATION, fn () => $this->administration->deactivate($owner, $owner));

        $this->assertSame(
            [
                ['account.deactivated', null],
                ['account.reactivated', null],
                ['account.locked', null],
                ['account.unlocked', null],
                ['account.roles_changed', null],
                ['account.change_refused', 'self_deactivation'],
            ],
            array_map(fn (SecurityEvent $event) => [$event->type->value, $event->cause], $this->events)
        );

        $this->assertSame((string) $target->getId(), $this->events[0]->userId);
        $this->assertSame((string) $owner->getId(), $this->events[0]->extra['actor_id']);
        $this->assertSame('ROLE_SUPPORT', $this->events[4]->extra['roles_before']);
        $this->assertSame('ROLE_MANAGER', $this->events[4]->extra['roles_after']);
        $this->assertSame('account.deactivated', $this->events[5]->extra['action']);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles, ?string $email = null): User
    {
        $user = (new User())
            ->setEmail($email ?? $username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setRoles($roles)
            ->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function assertRefused(
        AccountAdministrationRefusal $refusal,
        callable $change,
        string $message = '',
    ): AccountAdministrationException {
        try {
            $change();
        } catch (AccountAdministrationException $exception) {
            $this->assertSame($refusal, $exception->refusal, $message);

            return $exception;
        }

        $this->fail(sprintf('Expected the refusal "%s". %s', $refusal->value, $message));
    }
}
