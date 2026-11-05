<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Wexample\SymfonyHelpers\Voter\AbstractEntityVoter;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Membership;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Note;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\NoteComment;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Secret;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\Team;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\MembershipAppKernel;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * #[AccessScope]: a record voted on as what it hangs from says — its group,
 * its parent, its owner, the catalog, the overseeing role.
 */
class AccessScopeTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private const array EVERY = [AbstractEntityVoter::VIEW, AbstractEntityVoter::EDIT, AbstractEntityVoter::DELETE];

    private EntityManagerInterface $entityManager;

    private Team $alpha;

    private Note $note;

    protected static function getKernelClass(): string
    {
        return MembershipAppKernel::class;
    }

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createDatabaseSchema();
        $this->alpha = $this->persist(new Team('Alpha'));
        $this->note = $this->persist(new Note($this->alpha, 'Note of Alpha'));
        $this->entityManager->flush();
    }

    public function testAGroupsRecordIsViewedByItsWatchersChangedByItsContributors(): void
    {
        $this->signIn($this->member('wat', ['watcher']));
        $this->assertSame([true, false, false], $this->votes($this->note));

        $this->signIn($this->member('con', ['contributor']));
        $this->assertSame([true, true, true], $this->votes($this->note));

        // A grant including the one asked holds it.
        $this->signIn($this->member('adm', ['administrator']));
        $this->assertSame([true, true, true], $this->votes($this->note));

        $this->signIn($this->createUser('out'));
        $this->assertSame([false, false, false], $this->votes($this->note));

        $this->signIn($this->createUser('boss', ['ROLE_PLATFORM_ADMIN']));
        $this->assertSame([true, true, true], $this->votes($this->note));
    }

    public function testARecordOfNoGroupFallsToTheCatalog(): void
    {
        $shared = $this->persist(new Note(null, 'Shared'));
        $this->entityManager->flush();

        $this->signIn($this->createUser('any'));
        $this->assertSame([true, false, false], $this->votes($shared));

        $this->signIn($this->createUser('boss', ['ROLE_PLATFORM_ADMIN']));
        $this->assertSame([true, true, true], $this->votes($shared));
    }

    public function testAChildIsDecidedByItsParentAndAlwaysByItsOwner(): void
    {
        $author = $this->member('author', ['watcher']);
        $comment = $this->persist(new NoteComment($this->note, $author));
        $orphan = $this->persist(new NoteComment($this->note));
        $this->entityManager->flush();

        // The author changes its comment, its grant on the note would not let it.
        $this->signIn($author);
        $this->assertSame([true, true, true], $this->votes($comment));
        $this->assertSame([true, false, false], $this->votes($orphan));

        $this->signIn($this->member('con', ['contributor']));
        $this->assertSame([true, true, true], $this->votes($comment));

        $this->signIn($this->createUser('out'));
        $this->assertSame([false, false, false], $this->votes($comment));
    }

    public function testARecordHangingFromNothingIsTheOverseersAlone(): void
    {
        $secret = $this->persist(new Secret());
        $this->entityManager->flush();

        $this->signIn($this->member('adm', ['administrator']));
        $this->assertSame([false, false, false], $this->votes($secret));

        $this->signIn($this->createUser('boss', ['ROLE_PLATFORM_ADMIN']));
        $this->assertSame([true, true, true], $this->votes($secret));
    }

    public function testARecordWithoutAScopeIsLeftToOtherVoters(): void
    {
        $this->signIn($this->createUser('boss', ['ROLE_PLATFORM_ADMIN']));

        // Nobody votes: refused, whatever the role.
        $this->assertFalse($this->isGranted(AbstractEntityVoter::VIEW, $this->alpha));
    }

    /**
     * @return list<bool>
     */
    private function votes(object $subject): array
    {
        return array_map(fn (string $attribute) => $this->isGranted($attribute, $subject), self::EVERY);
    }

    private function member(string $username, array $grants): User
    {
        $user = $this->createUser($username);
        $this->persist(new Membership($this->alpha, $user, $grants));
        $this->entityManager->flush();

        return $user;
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
     * @template T of object
     * @param T $entity
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
}
