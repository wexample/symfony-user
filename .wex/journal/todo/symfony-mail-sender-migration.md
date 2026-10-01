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
      $locale, // the account's language once AbstractUser has one, else null
  );
  ```
  Then drop the class's own handling of the translator domain and the transport. The templates and their `.trans.yml` stay where they are; they are framed by the layout without any change.
- [ ] The account's language: Sapiens is going French / English (its todo `c12579ca6d0e`, point 8) and will want its mails in the recipient's language. A language stored on `AbstractUser` would be passed as `$locale` above. This is a separate decision for this package.
- [ ] Tests: the existing mail tests keep passing. Add one test where a link mail's text part contains the link.
- [ ] Unchanged: `SendSecurityMessage` and its handler. Queueing without the secret and building it in the worker is the pattern `MailSenderService` documents.

## Not now: symfony-user-demo's mailbox

`SessionSecurityMessageSenderService` keeps the last link in the visitor's session, so each visitor sees only their own links. symfony-mail's mailbox (`symfony-mail-ds`, page `/mailbox/`) shows every mail, and is routed in dev and test only. Replacing the demo's mailbox with it waits for the owner's security pass on the public demos.
