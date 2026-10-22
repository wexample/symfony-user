# Sapiens — account activation and re-sent password mails, sent asynchronously and traced

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). Nobody self-registers in Sapiens: an administrator creates an account (name, email), and the person receives a mail to choose their first password. Closes, in Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`, section *Transactional email*: **Account activation mail carrying a magic link**, **Manual re-send of a password-generation mail from the admin surface**, **Mail templates overridable by the application**, **Asynchronous sending through messenger, with retry and a failed queue**, **Send trace — what, to whom, when — without the sensitive body**, **Mails captured locally, never leaving the machine in development**.

Do `sapiens-redact-secrets-in-logged-exceptions.md` first: it is small, and these mails carry links.

## 1. Activation

- `AccountAdministrationService` (or a sibling) creates an account **not activated, with no password**, and sends an activation mail. The link lands on the set-password page (the `password_reset` flow already proves the mailbox and signs in afterwards).
- The link expires — configurable, days rather than minutes (an invitation is read later than a reset). An expired link leads to a page saying to ask the administrator, not to a generic error.
- Setting the password activates the account. The second factor (`two_factor.required`, `app_required_roles`) and the terms gate then apply as on any login.
- A distinct mail template from the reset one: the wording differs ("your account was created by …").

## 2. Re-send from the admin surface

- A service call re-sends the activation mail to a not-yet-activated account, and a "choose a new password" mail to an activated one. The previous links die.
- Goes through `AccountAdministrationService` rules: the actor must manage the target (`manages`, the application guard).
- Journalled (`account.activation_sent`, `account.password_mail_sent`, actor id).

## 3. Sending

- Mails go through Symfony Messenger when it is configured, so a request never waits on the mail provider — this also closes the timing difference noted in the generic-failure notice (only an existing account triggers a mail). Retry and `failed` queue are the application's transport config; document it.
- Templates overridable by the application, as the security mails already are.
- Send trace: one journal entry per mail — type, recipient user id (not the address in clear if avoidable), time, transport outcome — **never the link or the body**.
- Development: document capturing mails locally (Mailpit or the `null://` / file transport) so nothing leaves the machine.

## Tests

- Created account: not activated, no password, activation mail sent; the link sets the password and activates.
- Expired activation link → the explanatory page.
- Re-send kills the previous link; refused when the actor does not manage the target.
- With messenger configured, sending is dispatched, not synchronous.
- No journal record contains a link or a mail body.

## Plan

Author: agent:symfony-user

- [x] A pending account is enabled with no password: it cannot sign in, a deactivation kills its links, setting a password activates it. No new column.
- [x] `AccountAdministrationService::createAccount()` and `sendPasswordMail()`, under the administration rules.
- [x] Activation link: the reset link mechanics, its own route, lifetime (`activation.link_lifetime`, 7 days), template, and a page for a dead link.
- [x] Link mails go through Messenger as `SendSecurityMessage` (account id, type, target path): the handler builds the link when it sends, so no link is ever stored in a transport or the failed queue. Two-factor codes stay synchronous.
- [x] One `security_message.sent` / `.failed` journal entry per mail, without the link.
- [x] Tests, docs (Messenger routing, retry, failed queue, worker `default_uri`, local mail capture).

## Work log

- The detail checked against the code: right — no creation flow, no re-send, mails sent within the request through `MailerInterface`, no per-mail trace. The redaction todo was done first (`e9ed96b`).
- Pending = enabled with no password. Symfony refuses a null password before hashing: the authenticator now spends a dummy password check on such an account, as for an unknown address, so the timing does not tell pending accounts apart.
- The activation link is the reset link's signature (password and email), on `/password/activate`, lifetime `activation.link_lifetime`. It dies when the password is chosen, the address changes, or the account is disabled or locked.
- **Pushed back:** "re-send kills the previous link". Without a new column on every application's user table there is nothing to sign that a re-send changes; the earlier link went to the same mailbox, and dies with the address, the password, a deactivation, or its expiry. Say if the column is wanted.
- Mail wording: the sender receives no actor, so the template reads "An account was created for you", not the creator's name; the application overrides the template to name them.
- `SendSecurityMessage` carries the account class, id, type and target path; `SendSecurityMessageHandler` re-reads the account and builds the link when it sends — a disabled, locked or (for an activation) activated account gets nothing.
- Found: the Flex recipe routes `SendEmailMessage` to `async`, so a mail handed to `MailerInterface` is queued rendered, link included. The mailer sender now hands mails to the transport directly. Proved by the test, with that routing on: through the mailer, the queue held a second message.
- Found, not this package's: symfony-loader adds its Twig globals (`render_pass`) at the first page it renders; once a mail has initialized Twig in the same process, every page fails ("Unable to add global"). Hits a long-running worker, or a request rendering a mail then a page. The activation test renders a page first.
- Checked in the design-system app over HTTP: a magic link request lands in the demo mailbox, `messenger_messages` holds no link, `/password/activate` renders.
- Tests: `AccountActivationTest` (4), `AsyncSecurityMessageTest` (2), `SecurityMessageServiceTest` (1), a timing test; each rule mutation-checked; suite 98 green. A minimal fixture layout lets the package's pages render.

## Reply

1. **Real gap, implemented, no demo** — one push-back (re-send does not kill earlier links). Committed with this file.
2. **What the package does now.**
   - `AccountAdministrationService::createAccount(actor, account)`: the account, built by the application, is written enabled with no password, under the administration rules, and its holder gets an activation mail (`account_activation` template). Choosing the password activates it and signs in; second factor and gates apply.
   - `activation.link_lifetime` (default 7 days). A dead link shows a page saying to sign in or ask for a new link.
   - `sendPasswordMail(actor, target)`: the activation mail again, or a choose-a-new-password mail to an activated account; refused for a disabled or locked one (`account_inactive`), and under `manages` and the guards.
   - Journal: `account.created`, `account.activation_sent`, `account.password_mail_sent` (with `actor_id`), `account.activated`; each mail `security_message.sent` / `.failed` with its type — never link, code or body.
   - Link mails go through Messenger as `SendSecurityMessage`, without their link; the worker builds it. Codes stay synchronous.
   - Templates overridable under `templates/bundles/WexampleSymfonyUserBundle/mails/`.
3. **Found on the way.** The Flex `SendEmailMessage` routing would have queued rendered links (fixed: transport used directly). symfony-loader's late Twig globals break pages after a mail (for its owner). Pending accounts answered login faster (fixed).
4. **Notice for the application agent.**

> ```yaml
> framework:
>     messenger:
>         failure_transport: failed
>         transports:
>             async:
>                 dsn: '%env(MESSENGER_TRANSPORT_DSN)%'
>                 retry_strategy: { max_retries: 3, multiplier: 2 }
>             failed: 'doctrine://default?queue_name=failed'
>         routing:
>             Wexample\SymfonyUser\Message\SendSecurityMessage: async
>     router:
>         default_uri: '%env(APP_URL)%'
> wexample_symfony_user:
>     activation:
>         link_lifetime: 604800
> ```
> - Your admin screen calls `createAccount($this->getUser(), $account)` with the account built (name, email, roles, establishment), and `sendPasswordMail()` for the re-send button; map the refusal codes, `account_inactive` included.
> - Run a worker (`messenger:consume async`); without it, routed mails wait.
> - Override `mails/account_activation.html.twig` and its `.trans.yml` to name Home Habilis and the creator.
> - Development: `MAILER_DSN=null://null`, or a Mailpit container with `MAILER_DSN=smtp://mailpit:1025`.
> - An earlier activation link stays valid until it expires or the password is chosen (see the push-back).
