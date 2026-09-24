<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Fixtures\App\Repository\UserRepository;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

class UserRepositoryTest extends KernelTestCase
{
    use DatabaseTestTrait;

    private EntityManagerInterface $entityManager;

    private UserRepository $repository;

    private User $user;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->createDatabaseSchema();
        $this->repository = self::getContainer()->get(UserRepository::class);

        $this->user = (new User())
            ->setEmail('jane@example.com')
            ->setUsername('jane')
            ->setPassword('secret');

        $this->entityManager->persist($this->user);
        $this->entityManager->flush();
    }

    public function testFindsByEmailWhateverTheCase(): void
    {
        $this->assertSame($this->user, $this->repository->findOneByUserIdentifier(' Jane@Example.com '));
    }

    public function testFindsByUsername(): void
    {
        $this->assertSame($this->user, $this->repository->findOneByUserIdentifier('JANE'));
    }

    public function testUnknownOrEmptyIdentifierFindsNobody(): void
    {
        $this->assertNull($this->repository->findOneByUserIdentifier('john@example.com'));
        $this->assertNull($this->repository->findOneByUserIdentifier('john'));
        $this->assertNull($this->repository->findOneByUserIdentifier('  '));
    }

    public function testSecurityProviderLoadsByEmailOrUsername(): void
    {
        $provider = self::getContainer()->get('security.user.provider.concrete.users');

        $this->assertSame($this->user, $provider->loadUserByIdentifier('jane'));
        $this->assertSame($this->user, $provider->loadUserByIdentifier('jane@example.com'));

        $this->expectException(UserNotFoundException::class);
        $provider->loadUserByIdentifier('john');
    }

    public function testUpgradePasswordIsStored(): void
    {
        $this->repository->upgradePassword($this->user, 'rehashed');
        $this->entityManager->clear();

        $this->assertSame('rehashed', $this->repository->findOneByUserIdentifier('jane')->getPassword());
    }
}
