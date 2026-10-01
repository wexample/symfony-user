<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;
use Wexample\SymfonyUser\Tests\Traits\DatabaseTestTrait;

/**
 * A whole walk — failed logins, second factor, logout, reset, magic link —
 * leaves one journal entry per fact, with its real cause, and no log record
 * of any channel holds a secret the walk used.
 */
class SecurityJournalTest extends WebTestCase
{
    use DatabaseTestTrait;

    private const string PASSWORD = 'Sentinel-Password-4117';
    private const string NEW_PASSWORD = 'Sentinel-New-Password-9023';
    private const string UNKNOWN_ADDRESS = 'sentinel-unknown@example.com';

    private KernelBrowser $client;

    private EntityManagerInterface $entityManager;

    /**
     * @var list<string> every secret the walk used: none may reach a log
     */
    private array $secrets = [self::PASSWORD, self::NEW_PASSWORD, self::UNKNOWN_ADDRESS];

    /**
     * @var list<LogRecord> the test client resets the handler at every request:
     *                      the records are gathered after each one
     */
    private array $records = [];

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->client->disableReboot();
        $this->entityManager = $this->createDatabaseSchema();
        self::getContainer()->get('cache.rate_limiter')->clear();
        self::getContainer()->get('cache.app')->clear();

        $this->entityManager->persist(
            (new User())
                ->setEmail('jane@example.com')
                ->setUsername('jane')
                ->setPassword(self::PASSWORD)
                ->setEnabled(true)
        );
        $this->entityManager->flush();
        $this->getLogHandler()->clear();
    }

    public function testAWholeWalkIsJournaledWithoutASecret(): void
    {
        // Failed logins, each with its real cause.
        $this->login(self::UNKNOWN_ADDRESS, self::PASSWORD);
        $this->login('jane', 'Sentinel-Wrong-Password-3311');
        $this->secrets[] = 'Sentinel-Wrong-Password-3311';

        // The password, then the second factor: a wrong code, the right one.
        $this->login('jane', self::PASSWORD);
        $code = $this->getLastCode();
        $this->secrets[] = $code;
        $this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $code === '000000' ? '111111' : '000000']);
        $this->post('form-two_factor_code_form', 'two_factor_code_form', ['code' => $code]);
        $this->get('/logout');

        // A reset, for the account and for an unknown address.
        $this->post('form-password_reset_request_form', 'password_reset_request_form', ['identifier' => 'jane']);
        $resetLink = $this->getLastLink();
        $this->post('form-password_reset_request_form', 'password_reset_request_form', ['identifier' => self::UNKNOWN_ADDRESS]);
        $this->get($resetLink);
        $this->post('form-set_password_form', 'set_password_form', [
            'new_password' => ['first' => self::NEW_PASSWORD, 'second' => self::NEW_PASSWORD],
        ]);
        $this->get('/logout');

        // A magic link, asked for and followed — after a page missing on the
        // way, whose 404 quotes the link as its referer.
        $this->post('form-magic_link_request_form', 'magic_link_request_form', ['identifier' => 'jane']);
        $magicLink = $this->getLastLink();
        $this->client->request('GET', '/missing-page', server: ['HTTP_REFERER' => $magicLink]);
        $this->assertResponseStatusCodeSame(404);
        $this->gatherRecords();
        $this->get($magicLink);

        $this->assertJournal([
            ['login.failed', 'unknown_user', 'password'],
            ['login.failed', 'bad_password', 'password'],
            ['login.second_factor_required', null, 'password'],
            ['security_message.sent', null, null],
            ['second_factor.code_sent', null, null],
            ['second_factor.failed', 'invalid', null],
            ['second_factor.succeeded', null, null],
            ['login.succeeded', null, 'second_factor'],
            ['logout', null, null],
            ['password.reset_requested', null, null],
            ['security_message.sent', null, null],
            ['password.reset_requested', 'unknown_user', null],
            ['password.reset', null, null],
            ['login.succeeded', null, 'password_reset'],
            ['logout', null, null],
            ['magic_link.requested', null, null],
            ['security_message.sent', null, null],
            ['login.succeeded', null, 'magic_link'],
        ]);

        $unknown = $this->getJournal()[0];
        $this->assertNull($unknown['user_id']);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{16}$/', $unknown['extra']['identifier_fingerprint']);
        $this->assertSame('main', $unknown['firewall']);
        $this->assertNotEmpty($unknown['ip']);
        $this->assertNotEmpty($unknown['request_id']);
        $this->assertStringEndsWith('+00:00', $unknown['occurred_at']);

        $this->assertNoSecretInAnyLog();
    }

    /**
     * @param list<array{0: string, 1: ?string, 2: ?string}> $expected type, cause, method
     */
    private function assertJournal(array $expected): void
    {
        $this->assertSame(
            $expected,
            array_map(
                static fn (array $entry): array => [$entry['type'], $entry['cause'], $entry['method']],
                $this->getJournal()
            )
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function getJournal(): array
    {
        return array_values(array_map(
            static fn (LogRecord $record): array => $record->context,
            array_filter(
                $this->records,
                static fn (LogRecord $record): bool => $record->channel === 'user_security'
            )
        ));
    }

    private function assertNoSecretInAnyLog(): void
    {
        $this->assertNotEmpty($this->records);
        // Formatted as a handler would write it: exceptions in the context
        // come out with their message.
        $formatter = new LineFormatter(allowInlineLineBreaks: true);
        $formatter->includeStacktraces(false);

        foreach ($this->records as $record) {
            $dump = $formatter->format($record);

            foreach ($this->secrets as $secret) {
                $this->assertStringNotContainsString(
                    $secret,
                    (string) $dump,
                    sprintf('A "%s" record of the "%s" channel holds a secret of the walk.', $record->message, $record->channel)
                );
            }
        }
    }

    private function getLogHandler(): TestHandler
    {
        return self::getContainer()->get('monolog.handler.test');
    }

    private function login(string $identifier, string $password): void
    {
        $this->post('form-login_form', 'login_form', ['identifier' => $identifier, 'password' => $password]);
    }

    private function getLastCode(): string
    {
        preg_match('#<strong>(\d{6})</strong>#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);

        return $matches[1];
    }

    /**
     * The link of the last mail; its signature joins the secrets.
     */
    private function getLastLink(): string
    {
        preg_match('#href="([^"]+)"#', (string) $this->getMailerMessage()->getHtmlBody(), $matches);
        $link = html_entity_decode($matches[1]);
        parse_str((string) parse_url($link, PHP_URL_QUERY), $query);
        $this->secrets[] = $query['hash'];

        return $link;
    }

    private function get(string $url): void
    {
        $this->client->request('GET', $url);
        $this->gatherRecords();
    }

    private function post(string $name, string $formName, array $data): ?array
    {
        $this->client->request(
            'POST',
            '/_forms/submit/' . $name,
            [$formName => $data + ['_token' => 'csrf-token']],
            [],
            ['HTTP_ACCEPT' => 'application/json', 'HTTP_ORIGIN' => 'http://localhost']
        );
        $this->gatherRecords();

        return json_decode($this->client->getResponse()->getContent(), true);
    }

    private function gatherRecords(): void
    {
        array_push($this->records, ...$this->getLogHandler()->getRecords());
    }
}
