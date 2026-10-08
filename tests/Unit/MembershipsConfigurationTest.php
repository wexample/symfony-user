<?php

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Wexample\SymfonyUser\DependencyInjection\Configuration;
use Wexample\SymfonyUser\Service\GrantHierarchyService;

class MembershipsConfigurationTest extends TestCase
{
    private const array GRANTS = [
        'administrator' => ['contributor'],
        'contributor' => ['watcher'],
        'client' => ['watcher'],
        'watcher' => [],
    ];

    public function testTheGrantsReachTheOnesTheyInclude(): void
    {
        $hierarchy = new GrantHierarchyService($this->process(['grants' => self::GRANTS])['grants']);

        $this->assertSame(['administrator', 'contributor', 'client', 'watcher'], $hierarchy->getGrants());
        $this->assertEqualsCanonicalizing(['administrator', 'contributor', 'watcher'], $hierarchy->getReachableGrants(['administrator']));
        $this->assertEqualsCanonicalizing(['client', 'watcher'], $hierarchy->getReachableGrants(['client', 'unknown']));
        $this->assertFalse($hierarchy->includes(['client'], 'contributor'));
    }

    public static function provideInvalidMemberships(): iterable
    {
        yield 'an undeclared grant included' => [['grants' => ['administrator' => ['editor']]], '"administrator" includes "editor"'];
        yield 'a cycle' => [['grants' => ['a' => ['b'], 'b' => ['c'], 'c' => ['a']]], '"a" includes itself'];
        yield 'a role for an undeclared grant' => [['grants' => self::GRANTS, 'roles' => ['editor' => 'ROLE_EDITOR']], 'memberships.roles: "editor"'];
        yield 'an undeclared administering grant' => [['grants' => self::GRANTS, 'administer_grant' => 'owner'], 'memberships.administer_grant: "owner"'];
    }

    #[DataProvider('provideInvalidMemberships')]
    public function testAnInvalidDeclarationIsRefused(array $memberships, string $message): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage($message);

        $this->process($memberships);
    }

    private function process(array $memberships): array
    {
        return (new Processor())->processConfiguration(
            new Configuration(),
            [['memberships' => $memberships]]
        )['memberships'];
    }
}
