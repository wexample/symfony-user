# Send symfony-user's mails through symfony-mail

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:symfony-mail

## Context

`wexample/symfony-mail` now ships `MailSenderService`, the sender every mail of an application goes through. It does for every mail what `SecurityMessageMailerSenderService` does for symfony-user alone:
- texts beside the template, read as `@mail::`, subject included;
- the sender taken from `framework.mailer.headers.From`;
- the mail handed to the transport, not to the mailer, so a rendered mail with its link never sits in a queue.

It adds what symfony-user's mails lack today:
- **a layout** (header with the application name, footer), which an application overrides once through `wexample_symfony_mail.layout`;
- **a text part that keeps the link's URL**. Today the text part is built by Symfony's default converter, which drops the `href`, so a text-only client gets "Here is your sign-in link:" without the link;
- **an explicit locale per mail**, with the default locale as fallback, never the request's. This matters because link mails are rendered by a Messenger worker.

Documentation: `symfony-mail/.wex/knowledge/usage/sending.md.j2`.

## To do

- [ ] Require `wexample/symfony-mail`.
- [ ] Rebuild `SecurityMessageMailerSenderService::send()` on `MailSenderService::send()`:
  ```php
  $this->mailSender->send(
      (new TemplatedEmail())
          ->to(new Address((string) $user->getEmail()))
          ->htmlTemplate('@WexampleSymfonyUserBundle/mails/'.$type->value.'.html.twig')
          ->context(['value' => $value, 'expires_at' => $expiresAt]),
      $user->getLocale(),
  );
  ```
  Then drop the class's own handling of the translator domain and the transport. The templates and their `.trans.yml` stay where they are; they are framed by the layout without any change.
- [x] The account's language: `AbstractUser` now implements `HasLocaleInterface` with `HasLocaleTrait`, a nullable `locale` column (done by agent:symfony-mail, with the owner's agreement). It is filled by the application, or by `RememberLocaleSubscriber` with `remember_locale: true`. Applications generate a migration for the column.
- [ ] Once symfony-mail is required: `AbstractUser` also implements `MailRecipientInterface`. It already has `getEmail()`. The sender then becomes `$this->mailSender->sendTo($user, $email)`, which takes the account's language.
- [ ] Tests: the existing mail tests keep passing. Add one test where a link mail's text part contains the link.
- [ ] Unchanged: `SendSecurityMessage` and its handler. Queueing without the secret and building it in the worker is the pattern `MailSenderService` documents.

## Done: symfony-user-demo's mailbox

`SessionSecurityMessageSenderService` and the demo's `/user/mailbox` page are removed (owner's decision, done by agent:symfony-mail). The demo sends its links and codes through this package's real sender, and they land in symfony-mail's development mailbox (`/mailbox/`, dev and test only). The authenticator code the old page also showed is now on `/user/authenticator`. Keeping each visitor's links to that visitor on a public demo is part of the owner's security pass.
