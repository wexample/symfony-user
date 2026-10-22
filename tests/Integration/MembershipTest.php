<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wexample\SymfonyUser\Enum\AccountAdministrationRefusal;
use Wexample\SymfonyUser\Enum\MembershipAdministrationRefusal;
use Wexample\SymfonyUser\Exception\AccountAdministrationException;
use Wexample\SymfonyUser\Exception\MembershipAdministrationException;
use Wexample\SymfonyUser\Service\AccountAdministrationService;
use Wexample\SymfonyUser\Service\MembershipAdministrationService;
use Wexample\SymfonyUser\Service\MembershipScopeService;
use Wexample\SymfonyUser\Service\MembershipService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Membership;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Note;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Organization;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Team;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\MembershipAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * administrator ⊃ contributor ⊃ watcher, client ⊃ watcher; administrator
 * gives ROLE_TEAM_ADMIN, watcher ROLE_TEAM_MEMBER; administrators administer
 * the accounts of their teams.
 */
class MembershipTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private EntityManagerInterface $entityManager;

    private MembershipService $memberships;

    private Team $alpha;

    private Team $beta;

    private Team $gamma;

    protected static function getKernelClass(): string
    {
        return MembershipAppKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createDatabaseSchema();
        $this->memberships = self::getContainer()->get('test.membership');

        $this->alpha = $this->persist(new Team('Alpha'));
        $this->beta = $this->persist(new Team('Beta'));
        $this->gamma = $this->persist(new Team('Gamma'));

        foreach ([$this->alpha, $this->beta, $this->gamma] as $team) {
            $this->persist(new Note($team, 'Note of ' . $team->getMembershipLabel()));
        }

        $this->entityManager->flush();
    }

    public function testAGrantIncludesTheOnesBelowIt(): void
    {
        $user = $this->createUser('ada');
        $this->join($this->alpha, $user, ['administrator']);
        $this->join($this->beta, $user, ['client']);

        $this->signIn($user);

        $this->assertTrue($this->isGranted('watcher', $this->alpha));
        $this->assertTrue($this->isGranted('contributor', $this->alpha));
        $this->assertFalse($this->isGranted('client', $this->alpha));

        $this->assertTrue($this->isGranted('watcher', $this->beta));
        $this->assertFalse($this->isGranted('contributor', $this->beta));

        $this->assertFalse($this->isGranted('watcher', $this->gamma));
    }

    public function testAnAccountHoldsTheGrantsOfTheOrganizationsItActsFor(): void
    {
        $organization = $this->persist(new Organization('Acme'));
        $this->join($this->alpha, $organization, ['contributor']);

        $user = $this->createUser('bob');
        $user->addOrganization($organization);
        $this->entityManager->flush();

        $this->signIn($user);

        $this->assertTrue($this->isGranted('contributor', $this->alpha));
        $this->assertFalse($this->isGranted('administrator', $this->alpha));
        $this->assertSame([$this->alpha], $this->memberships->getGroups($user));
        // Its own membership: it has none, the organization has one.
        $this->assertNull($this->memberships->getMembership($user, $this->alpha));
    }

    public function testRolesFollowTheGrantsWithoutANewLogin(): void
    {
        $user = $this->createUser('cleo');
        $this->signIn($user);

        $this->assertFalse($this->isGranted('ROLE_TEAM_MEMBER'));

        $membership = $this->join($this->alpha, $user, ['watcher']);

        $this->assertTrue($this->isGranted('ROLE_TEAM_MEMBER'));
        $this->assertFalse($this->isGranted('ROLE_TEAM_ADMIN'));

        $membership->setGrants(['administrator']);

        $this->assertTrue($this->isGranted('ROLE_TEAM_ADMIN'));
        // Reached through the role hierarchy, and through the grant hierarchy.
        $this->assertTrue($this->isGranted('ROLE_TEAM_MEMBER'));
        $this->assertFalse($this->isGranted('ROLE_PLATFORM_ADMIN'));
    }

    public function testAnAccountInNoGroupSeesNothing(): void
    {
        $this->signIn($this->createUser('dan'));

        $this->assertSame([], $this->readNotes());
    }

    public function testAnAccountSeesTheRecordsOfItsGroupsOnly(): void
    {
        $user = $this->createUser('eve');
        $this->join($this->alpha, $user, ['watcher']);
        $this->join($this->beta, $user, ['contributor']);
        $this->signIn($user);

        $this->assertSame(['Note of Alpha', 'Note of Beta'], $this->readNotes());
        $this->assertSame(['Note of Beta'], $this->readNotes('contributor'));
        $this->assertSame([], $this->readNotes('administrator'));
    }

    public function testARecordOfAnotherGroupIsNotFound(): void
    {
        $user = $this->createUser('fay');
        $this->join($this->alpha, $user, ['watcher']);
        $this->signIn($user);

        $scope = $this->getScope();
        $scope->assertInScope($this->alpha);

        $this->expectException(NotFoundHttpException::class);
        $scope->assertInScope($this->gamma);
    }

    public function testARecordOutOfTheGrantAskedIsNotFound(): void
    {
        $user = $this->createUser('gus');
        $this->join($this->alpha, $user, ['watcher']);
        $this->signIn($user);

        $this->expectException(NotFoundHttpException::class);
        $this->getScope()->assertInScope($this->alpha, 'contributor');
    }

    public function testAGroupAdministratorReachesTheAccountsOfItsGroupsOnly(): void
    {
        $administrator = $this->createUser('hal');
        $this->join($this->alpha, $administrator, ['administrator']);

        $inAlpha = $this->createUser('ivy');
        $this->join($this->alpha, $inAlpha, ['watcher']);

        $alsoInBeta = $this->createUser('joe');
        $this->join($this->alpha, $alsoInBeta, ['watcher']);
        $this->join($this->beta, $alsoInBeta, ['watcher']);

        $inNoGroup = $this->createUser('kim');

        $administration = $this->getAdministration();
        $administration->lock($administrator, $inAlpha);
        $this->assertTrue($inAlpha->isLocked());

        $this->assertOutOfScope(fn () => $administration->lock($administrator, $alsoInBeta));
        $this->assertOutOfScope(fn () => $administration->lock($administrator, $inNoGroup));
    }

    public function testAGroupAdministratorCreatesAnAccount(): void
    {
        $administrator = $this->createUser('lea');
        $this->join($this->alpha, $administrator, ['administrator']);

        $account = new User();
        $account->setEmail('new@example.com');

        $this->getAdministration()->createAccount($administrator, $account);

        $this->assertTrue($this->entityManager->contains($account));
    }

    public function testAPlatformAdministratorKeepsTheRulesOfAdministration(): void
    {
        $platform = $this->createUser('max', ['ROLE_PLATFORM_ADMIN']);

        $member = $this->createUser('ned');
        $this->join($this->alpha, $member, ['watcher']);
        $this->join($this->beta, $member, ['watcher']);

        $this->getAdministration()->lock($platform, $member);

        $this->assertTrue($member->isLocked());
    }

    public function testAGroupAdministratorManagesItsMembers(): void
    {
        $administrator = $this->createUser('ora');
        $this->join($this->alpha, $administrator, ['administrator']);
        $newcomer = $this->createUser('pam');

        $administration = $this->getMembershipAdministration();
        $membership = $administration->add($administrator, $this->alpha, $newcomer, ['watcher']);

        $this->assertSame(['watcher'], $this->memberships->getGrants($newcomer, $this->alpha));

        $administration->changeGrants($administrator, $membership, ['contributor']);
        $this->assertTrue($this->memberships->holds($newcomer, 'contributor', $this->alpha));

        $administration->remove($administrator, $membership);
        $this->entityManager->clear();

        $this->assertSame([], $this->memberships->getGroups($this->entityManager->find(User::class, $newcomer->getId())));
    }

    public function testMembersAreManagedByTheGroupAdministratorsOnly(): void
    {
        $administrator = $this->createUser('quinn');
        $this->join($this->alpha, $administrator, ['administrator']);
        $contributor = $this->createUser('rae');
        $this->join($this->beta, $contributor, ['contributor']);

        $administration = $this->getMembershipAdministration();

        $this->assertMembershipRefused(MembershipAdministrationRefusal::GROUP_NOT_ADMINISTERED, fn () => $administration->add($administrator, $this->beta, $this->createUser('sam'), ['watcher']));
        $this->assertMembershipRefused(MembershipAdministrationRefusal::GROUP_NOT_ADMINISTERED, fn () => $administration->add($contributor, $this->beta, $this->createUser('tom'), ['watcher']));

        // The administration page role administers every group.
        $platform = $this->createUser('uma', ['ROLE_TEAM_ADMIN']);
        $administration->add($platform, $this->beta, $administrator, ['watcher']);
        $this->assertTrue($this->memberships->holds($administrator, 'watcher', $this->beta));
    }

    public function testAGroupKeepsAnAdministrator(): void
    {
        $administrator = $this->createUser('vic');
        $own = $this->join($this->alpha, $administrator, ['administrator']);

        $administration = $this->getMembershipAdministration();

        $this->assertMembershipRefused(MembershipAdministrationRefusal::LAST_ADMINISTRATOR, fn () => $administration->changeGrants($administrator, $own, ['watcher']));
        $this->assertMembershipRefused(MembershipAdministrationRefusal::LAST_ADMINISTRATOR, fn () => $administration->remove($administrator, $own));

        $other = $administration->add($administrator, $this->alpha, $this->createUser('wes'), ['administrator']);
        $administration->remove($administrator, $own);

        $this->assertSame(['administrator'], $other->getGrants());
    }

    public function testAMemberJoinsOnceWithDeclaredGrants(): void
    {
        $administrator = $this->createUser('xan');
        $this->join($this->alpha, $administrator, ['administrator']);
        $member = $this->createUser('yul');
        $this->join($this->alpha, $member, ['watcher']);

        $administration = $this->getMembershipAdministration();

        $this->assertMembershipRefused(MembershipAdministrationRefusal::ALREADY_MEMBER, fn () => $administration->add($administrator, $this->alpha, $member, ['client']));
        $this->assertMembershipRefused(MembershipAdministrationRefusal::INVALID_GRANTS, fn () => $administration->add($administrator, $this->alpha, $this->createUser('zed'), []));
        $this->assertMembershipRefused(MembershipAdministrationRefusal::INVALID_GRANTS, fn () => $administration->add($administrator, $this->alpha, $this->createUser('abe'), ['owner']));
    }

    private function assertMembershipRefused(MembershipAdministrationRefusal $refusal, callable $action): void
    {
        try {
            $action();
            $this->fail('The change was made.');
        } catch (MembershipAdministrationException $exception) {
            $this->assertSame($refusal, $exception->refusal);
        }
    }

    private function assertOutOfScope(callable $action): void
    {
        try {
            $action();
            $this->fail('The change was made.');
        } catch (AccountAdministrationException $exception) {
            $this->assertSame(AccountAdministrationRefusal::TARGET_OUT_OF_SCOPE, $exception->refusal);
        }
    }

    /**
     * @return list<string>
     */
    private function readNotes(?string $grant = null): array
    {
        $queryBuilder = $this->entityManager
            ->createQueryBuilder()
            ->select('note')
            ->from(Note::class, 'note')
            ->orderBy('note.text');

        $this->getScope()->restrictToCurrentMember($queryBuilder, 'note.team', $grant);

        return array_map(
            static fn (Note $note) => $note->getText(),
            $queryBuilder->getQuery()->getResult()
        );
    }

    /**
     * @param list<string> $roles
     */
    private function createUser(string $username, array $roles = []): User
    {
        $user = new User();
        $user
            ->setEmail($username . '@example.com')
            ->setUsername($username)
            ->setRoles($roles)
            ->setEnabled(true);

        $this->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    /**
     * @param list<string> $grants
     */
    private function join(Team $team, User|Organization $member, array $grants): Membership
    {
        $membership = $this->persist(new Membership($team, $member, $grants));
        $this->entityManager->flush();

        return $membership;
    }

    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function persist(object $entity): object
    {
        $this->entityManager->persist($entity);

        return $entity;
    }

    private function signIn(User $user): void
    {
        self::getContainer()->get('security.token_storage')->setToken(
            new UsernamePasswordToken($user, 'main', $user->getRoles())
        );
    }

    private function isGranted(string $attribute, mixed $subject = null): bool
    {
        return self::getContainer()->get(AuthorizationCheckerInterface::class)->isGranted($attribute, $subject);
    }

    private function getScope(): MembershipScopeService
    {
        return self::getContainer()->get('test.membership_scope');
    }

    private function getMembershipAdministration(): MembershipAdministrationService
    {
        return self::getContainer()->get('test.membership_administration');
    }

    private function getAdministration(): AccountAdministrationService
    {
        return self::getContainer()->get('test.account_administration');
    }
}
