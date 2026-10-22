<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Event\SecurityEvent;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\ManagesAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * ROLE_PLATFORM_ADMIN manages the team administrators, ROLE_TEAM_ADMIN the
 * members, and neither inherits the rights of those they manage.
 */
class ManagedAdministrationTest extends KernelTestCase
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
        return ManagesAppKernel::class;
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

    public function testATeamAdministratorManagesMembersWithoutTheirRights(): void
    {
        $teamAdmin = $this->createUser('team-admin', ['ROLE_TEAM_ADMIN']);
        $member = $this->createUser('member', ['ROLE_MEMBER']);

        $this->administration->deactivate($teamAdmin, $member);
        $this->assertFalse($member->isEnabled());

        $this->administration->changeRoles($teamAdmin, $this->createUser('newcomer', []), ['ROLE_MEMBER']);

        self::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken($teamAdmin, 'main', $teamAdmin->getRoles())
        );
        $this->assertFalse(self::getContainer()->get('security.authorization_checker')->isGranted('ROLE_MEMBER'));
    }

    public function testNothingOutsideWhatTheActorManages(): void
    {
        $teamAdmin = $this->createUser('team-admin', ['ROLE_TEAM_ADMIN']);
        $platformAdmin = $this->createUser('platform-admin', ['ROLE_PLATFORM_ADMIN']);
        $this->createUser('other-platform-admin', ['ROLE_PLATFORM_ADMIN']);
        $member = $this->createUser('member', ['ROLE_MEMBER']);

        $this->assertRefused(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, fn () => $this->administration->changeRoles($teamAdmin, $member, ['ROLE_PLATFORM_ADMIN']));
        $this->assertRefused(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, fn () => $this->administration->deactivate($teamAdmin, $platformAdmin));

        // Not transitive: the platform administrator does not manage members.
        $this->assertRefused(AccountAdministrationRefusal::ROLE_NOT_ASSIGNABLE, fn () => $this->administration->lock($platformAdmin, $member));

        $this->administration->deactivate($platformAdmin, $teamAdmin);
        $this->assertFalse($teamAdmin->isEnabled());
    }

    public function testAnApplicationGuardRefusesAPair(): void
    {
        $teamAdmin = $this->createUser('team-admin', ['ROLE_TEAM_ADMIN']);
        $outsider = $this->createUser('outsider-member', ['ROLE_MEMBER']);
        $this->events = [];

        $this->assertRefused(AccountAdministrationRefusal::TARGET_OUT_OF_SCOPE, fn () => $this->administration->deactivate($teamAdmin, $outsider));

        $this->entityManager->refresh($outsider);
        $this->assertTrue($outsider->isEnabled());
        $this->assertSame('account.change_refused', $this->events[0]->type->value);
        $this->assertSame('target_out_of_scope', $this->events[0]->cause);
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles): User
    {
        $user = (new User())
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setPassword('secret')
            ->setRoles($roles)
            ->setEnabled(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function assertRefused(AccountAdministrationRefusal $refusal, callable $change): void
    {
        try {
            $change();
        } catch (AccountAdministrationException $exception) {
            $this->assertSame($refusal, $exception->refusal);

            return;
        }

        $this->fail(sprintf('Expected the refusal "%s".', $refusal->value));
    }
}
