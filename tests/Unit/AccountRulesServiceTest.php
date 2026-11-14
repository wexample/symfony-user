<?php

declare(strict_types=1);

namespace Wexample\SymfonyUser\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Wexample\SymfonyUser\Service\AccountRulesService;

/**
 * An address belongs to a domain when what follows its single @ is that
 * domain exactly, whatever the case: a subdomain is another domain.
 */
class AccountRulesServiceTest extends TestCase
{
    private AccountRulesService $rules;

    protected function setUp(): void
    {
        $this->rules = new AccountRulesService(new RoleHierarchy([]));
    }

    public function testAnAddressOfTheDomainIsIn(): void
    {
        $this->assertTrue($this->rules->isEmailInDomains('ada@example.com', ['example.com']));
        $this->assertTrue($this->rules->isEmailInDomains('ada@example.org', ['example.com', 'example.org']));
    }

    public function testTheCaseIsIgnoredOnBothSides(): void
    {
        $this->assertTrue($this->rules->isEmailInDomains('Ada@EXAMPLE.com', ['example.com']));
        $this->assertTrue($this->rules->isEmailInDomains('ada@example.com', ['Example.COM']));
        $this->assertTrue($this->rules->isEmailInDomains('ada@ÉCOLE.fr', ['école.fr']));
    }

    public function testSurroundingSpacesAreIgnored(): void
    {
        $this->assertTrue($this->rules->isEmailInDomains("  ada@example.com\n", ['example.com']));
    }

    public function testASubdomainOrAParentDomainIsAnotherDomain(): void
    {
        $this->assertFalse($this->rules->isEmailInDomains('ada@mail.example.com', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('ada@example.com', ['mail.example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('ada@notexample.com', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('ada@example.com.evil.org', ['example.com']));
    }

    public function testAnAddressThatIsNoAddressIsNeverIn(): void
    {
        $this->assertFalse($this->rules->isEmailInDomains('ada@evil.org@example.com', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('@example.com', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('example.com', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('ada@', ['example.com']));
        $this->assertFalse($this->rules->isEmailInDomains('ada@example.com', []));
    }
}
