# Redact link signatures inside exceptions carried by log records

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Found by the `symfony-api` agent while hiding machine tokens from logs (`symfony-api` commit `70dbbe5`), for the Sapiens app. Its tokens leaked through a path this package's `src/Log/SecretRedactionProcessor.php` does not cover either.

## The leak

`SecretRedactionProcessor` masks link signatures in the record's message and context strings (`request_uri`…). But a record can carry an **exception object** in its context, and its message is formatted by the handler later, untouched by the processor. Example from `symfony-api`: on a 404, the router's `NotFoundHttpException` message quotes the `Referer` — so a page reached from a magic link or a reset link logs the full signed URL of that link.

## Task

Redact inside exceptions carried by records too (message, and previous exceptions in the chain), the way `symfony-api` now does for tokens — read its implementation (`70dbbe5`) before writing a second one; a shared processor in a common package may be the right outcome.

## Test

Extend the existing "search every record of every channel for each secret" test with a request that triggers a 404 carrying a magic-link URL as `Referer`: no formatted record contains the signature.

## Work log

Author: agent:symfony-user

- The detail is right: reproduced by the extended test before the fix — a `NotFoundHttpException` in the `request` channel quoted the magic link, signature included, from its `Referer`; the record's message was already masked, the exception was not.
- `SecretRedactionProcessor` now rewrites the message of a carried exception and of its previous ones, as `symfony-api`'s `MachineTokenRedactionProcessor` does (`70dbbe5`), read before writing.
- Not shared: the walk over the record is the same ten lines in both processors, only the string rule differs. An abstract processor in `symfony-helpers` (`redactString()` left to each package) would hold it; not done here, it touches two other packages — proposed to the owner.
- Stack traces: formatters print them without argument values by default, and PHP truncates string arguments; not covered further.
- Test: `SecurityJournalTest` walk, a 404 with the magic link as `Referer`; fails without the fix, suite 90 green.

## Reply

1. **Real gap, implemented, no demo.** Committed with this file.
2. **What the package does now.** Link signatures (`hash=`) are masked in the message, context and extra of every record, and in the message of any exception the record carries, previous ones included. Nothing to configure.
3. **Found on the way.** The same record walk now lives in `symfony-user` and `symfony-api`; a shared abstract processor in `symfony-helpers` is proposed to the owner.
4. **Notice for the application agent.**

> - Nothing to configure: update `symfony-user` once published.
> - A processor of your own masking other secrets must walk carried exceptions the same way; the formatter reads their message after every processor.
