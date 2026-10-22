<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * The default firewall, without AccountPickerAuthenticator: no page, and a
 * crafted submission signs nobody in.
 */
class AccountPickerOffTest extends WebTestCase
{
    use DatabaseTestTrait;

    public function testNothingSignsInWithoutTheAuthenticator(): void
    {
        $client = static::createClient();
        $client->disableReboot();
        $entityManager = $this->createDatabaseSchema();
        $entityManager->persist((new User())->setEmail('jane@example.com')->setUsername('jane')->setPassword('secret')->setEnabled(true));
        $entityManager->flush();

        $client->request('GET', '/login/accounts');
        $this->assertResponseStatusCodeSame(404);

        $client->request(
            'POST',
            '/_forms/submit/form-account_picker_form',
            ['account_picker_form' => ['account' => 'jane@example.com', '_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );
        $this->assertNull(self::getContainer()->get('security.token_storage')->getToken());

        $client->request('GET', '/protected');
        $this->assertResponseRedirects();
    }
}
