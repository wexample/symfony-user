<?php

namespace Wexample\SymfonyUser\Tests\Integration;

use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mailer\Event\MessageEvent;
use Symfony\Component\Mime\Email;
use Wexample\SymfonyTranslations\Translation\Translator;
use Wexample\SymfonyUser\Enum\SecurityMessageType;
use Wexample\SymfonyUser\Service\SecurityMessageMailerSenderService;
use Wexample\SymfonyUser\Tests\Fixtures\App\Entity\User;

/**
 * Every text of the security mails comes from the yml next to their template.
 */
class SecurityMessageMailTest extends KernelTestCase
{
    public function testEveryMailIsTranslated(): void
    {
        foreach (['en' => 'Your sign-in code', 'fr' => 'Votre code de connexion'] as $locale => $subject) {
            $email = $this->send(SecurityMessageType::TWO_FACTOR_CODE, $locale);

            $this->assertSame($subject, $email->getSubject());
            $this->assertStringContainsString($locale === 'fr' ? 'Votre code de connexion :' : 'Your sign-in code:', $email->getHtmlBody());
            $this->assertStringContainsString('<strong>123456</strong>', $email->getHtmlBody());
        }

        foreach (SecurityMessageType::cases() as $type) {
            foreach (['en', 'fr'] as $locale) {
                $email = $this->send($type, $locale);

                // A missing key comes out as its domain, `::` and the key.
                $this->assertStringNotContainsString('::', $email->getSubject() . $email->getHtmlBody(), $type->value . ' ' . $locale);
            }
        }
    }

    private function send(SecurityMessageType $type, string $locale): Email
    {
        self::ensureKernelShutdown();
        self::bootKernel();
        $container = self::getContainer();
        $container->get(Translator::class)->setLocale($locale);

        $sent = null;
        $container->get('event_dispatcher')->addListener(
            MessageEvent::class,
            static function (MessageEvent $event) use (&$sent): void {
                $sent = $event->getMessage();
            },
            -1000
        );

        $container->get(SecurityMessageMailerSenderService::class)->send(
            (new User())->setEmail('jane@example.com'),
            $type,
            '123456',
            new DateTimeImmutable('+10 minutes')
        );

        $this->assertInstanceOf(Email::class, $sent);

        return $sent;
    }
}
