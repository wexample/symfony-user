# Sapiens — account administration guards: deactivation, role changes, domain and exclusivity rules

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). The package secures signing in, but has no service for *administering* an account: deactivating, reactivating, locking, changing roles are left to `setEnabled()` / `setRoles()` on the entity, with no rule and no trace. Sapiens' business rules (`.wex/knowledge/specifications/business-rules.md.j2`) need several guards there. This todo closes, in Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`:

- **Last-administrator guard: refusing to deactivate the last active account of a role**
- **Refusing self-deactivation**
- **Server-side validation restricting a role to an email domain**
- **Mutually exclusive roles** — the server-side half; the database constraint stays the application's
- **Repeated reset requests rate-limited** — a small, separate item, section 6

## 1. One service for every administrative change

An `AccountAdministrationService` (name yours) through which an administrator — and only through which — deactivates, reactivates, locks, unlocks, and changes the roles of an account. Each call receives the **acting** user and the **target** account, checks every rule below, applies, and journals. The entity setters stay, for fixtures and migrations, but the documentation says applications go through the service.

It reuses `AssignableRolesService::canAssign()`: an actor still cannot grant above themselves.

## 2. No self-deactivation

An actor cannot deactivate, lock, or remove the administrative role from **their own** account. Sapiens: "un HOME_HABILIS_IT ne peut pas se désactiver lui-même".

## 3. Last holder of a protected role

Configurable list of protected roles (Sapiens: `ROLE_HOME_HABILIS_IT`). Refuse any change — deactivation, lock, role removal — that would leave **no active account** holding that role (directly or through the hierarchy, consistent with `findByRoles()`). Without it, the platform becomes unmanageable with no recovery path.

Count server-side at the moment of the change; two administrators deactivating each other at the same time must not both succeed — take a lock or re-check inside the transaction.

## 4. A role restricted to an email domain

Configurable map role → allowed domains (Sapiens: `ROLE_HOME_HABILIS_IT` → `home-habilis.com`). Refuse granting the role to an account whose email is outside the domain, **and** refuse changing the email of an account holding the role to an address outside it. Compare the domain exactly, case-insensitively, after the last `@` — `x@home-habilis.com.evil.fr` and `x@evil.fr?@home-habilis.com` must fail.

## 5. Mutually exclusive roles

Configurable groups of roles that cannot be held together (Sapiens: `ROLE_HOME_HABILIS_IT` alone, never combined with any establishment role). Refuse the role change that would combine them, with a message naming the conflict.

The database `CHECK` constraint Sapiens also wants is the application's job — the package does not know its schema — but say in the docs that the service is the first line and the constraint the last.

## 6. Rate-limit reset requests

`login_throttling` already covers attempts. Requests to `/password/forgot` (and magic-link requests) are not limited per address: one can flood a mailbox. Limit per address **and** per IP, with the same response whether limited or not — a "too many requests" visible only for existing addresses would be an enumeration oracle.

## Errors and journal

- A refusal is an explicit domain exception carrying a stable code (`self_deactivation`, `last_protected_role_holder`, `email_domain_not_allowed`, `exclusive_roles`, `role_not_assignable`), so an admin form can show a clear message.
- Every applied change emits a `SecurityEvent`: `account.deactivated`, `.reactivated`, `.locked`, `.unlocked`, `account.roles_changed` (before / after), with the actor's id. Every refusal too, with its code. Role changes being journalled is also a Sapiens line (*Role changes journalized*, Traceability).

## Tests

- An actor cannot deactivate or lock themselves, nor drop their own admin role.
- The last active holder of a protected role cannot be deactivated, locked or stripped of it; with two holders, one can.
- Two concurrent deactivations of the last two holders do not both succeed.
- Domain-restricted role refused outside the domain, including the tricky addresses above; changing a holder's email outside the domain refused.
- Exclusive roles refused together.
- Each applied change and each refusal produces its journal entry.
- Reset and magic-link requests limited per address and per IP, with an identical response.

## Plan

Author: agent:symfony-user

- [x] Options `administration.{protected_roles, role_email_domains, exclusive_roles}` and `request_limit.{per_identifier, per_ip}`.
- [x] `AccountRulesService`: the account invariants (email domain, exclusive roles), whoever changes the account; a Doctrine guard applies them on every flush.
- [x] `AccountAdministrationService`: deactivate, reactivate, lock, unlock, change roles — actor rules (self, outranked target, assignable roles), last protected holder under a row lock, journal of changes and refusals.
- [x] `AccountAdministrationException` with a stable code.
- [x] Reset and magic-link requests limited per address and per IP, same answer.
- [x] Tests, docs.

## Work log

- The detail checked against the code: right. No administration service existed, `AssignableRolesService::canAssign()` covered granting only, and the reset and magic-link requests had no limit. One mistake: "compare the domain after the last `@`" lets `x@evil.fr?@home-habilis.com` through — the address must hold exactly one `@`, the domain is what follows it.
- Exclusivity counts the roles **given**, not those reached through the hierarchy: Sapiens' IT administrator must reach the establishment roles to administer their holders, and would otherwise conflict with them. The domain rule counts reached roles.
- Added beyond the request: an actor cannot act on an account holding a role they do not reach (deactivating a super administrator), code `role_not_assignable`; removing one's own role is `self_demotion`, distinct from `self_deactivation`.
- Domain and exclusivity are invariants (`AccountRulesService`), applied on every flush by `AccountRulesGuardSubscriber`, so an email change in the application's own profile form is caught too.
- Last holder: the active holders are read `FOR UPDATE`, the target included, inside the change's transaction. Checked on the design-system app's Postgres: a session holding the lock and disabling the other holder, the service waiting then refusing; with the lock removed, both accounts ended disabled.
- Request limit keyed by account when the address has one (username and email share it), by fingerprint otherwise; both limits are consumed on every request.
- Tests: `AccountAdministrationTest` (6), `MailRequestLimitTest` (2), each rule mutation-checked; suite 86 green. The concurrency is not in the suite: SQLite in memory has one connection.
- Found on the way: symfony-testing's `TestKernel` sets `security.role_hierarchy.roles` itself, over any `security.role_hierarchy` of a fixture app; the fixture sets the parameter again.
- No demo: no administration screen exists to show it on.

## Reply

1. **Real gap, implemented, no demo** — tests prove it. Committed with this file.
2. **What the package does now.**
   - `AccountAdministrationService`: `deactivate`, `reactivate`, `lock`, `unlock`, `changeRoles(actor, target, roles)`. Refusals throw `AccountAdministrationException`, its `refusal` one of `role_not_assignable`, `self_deactivation`, `self_demotion`, `last_protected_role_holder`, `email_domain_not_allowed`, `exclusive_roles`, its `roles` the ones concerned.
   - `administration.protected_roles` (default `[]`): never left without an active holder, counted under a row lock.
   - `administration.role_email_domains` (default `{}`): held, directly or through the hierarchy, only by an address of exactly one of these domains.
   - `administration.exclusive_roles` (default `{}`): never **given** together; reaching a role through the hierarchy does not count.
   - These two are checked on every flush of an account, whoever changes it; `AccountRulesService::assertValid()` lets a form refuse first.
   - Journal: `account.deactivated`, `.reactivated`, `.locked`, `.unlocked`, `account.roles_changed` (`roles_before` / `roles_after`), `account.change_refused` with the code; `extra.actor_id` on each.
   - `request_limit.per_identifier` (3) and `per_ip` (20), per hour, on reset and magic-link requests together: past it, the same answer, no mail, `rate_limited` in the journal.
3. **Found on the way.** The request's own domain rule ("after the last `@`") accepts its counter-example; the address must hold one `@`.
4. **Notice for the application agent.**

> ```yaml
> wexample_symfony_user:
>     administration:
>         protected_roles: [ROLE_HOME_HABILIS_IT]
>         role_email_domains:
>             ROLE_HOME_HABILIS_IT: [home-habilis.com]
>         exclusive_roles:
>             ROLE_HOME_HABILIS_IT: [<every establishment role>]
> ```
> - Every administrative change goes through `AccountAdministrationService`, called with the signed-in user as actor; who reaches the administration screens stays your access control. Map each refusal code to a message.
> - `ROLE_HOME_HABILIS_IT` must reach the roles of the accounts it administers in `role_hierarchy`: an actor cannot act on an account holding a role they do not reach. That does not conflict with the exclusivity, which counts given roles only.
> - A profile form changing an email calls `AccountRulesService::assertValid()` before flushing; otherwise the flush throws.
> - The `CHECK` constraint on exclusive roles is your migration.
> - Request limits need no setting; the defaults are 3 per address and 20 per IP, an hour.
