# Post-login destination by role, and journalled access denials

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Requested by an application (Sapiens) with several surfaces, each reserved to some roles. Generic. Closes, in Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`: **Post-login routing by role, declared rather than coded per controller** and **Route-level access guard returning 403 and journalizing route, role, scope, IP, UTC timestamp**.

## 1. Post-login destination by role

- When no target path was saved (a direct visit to `/login`), the destination after a complete login — second factor and gates included — comes from configuration: an ordered list of role → route; the first role the user holds (through the hierarchy) wins; a default route otherwise.
- A saved target path still wins, as today — but only if the user may reach it; otherwise fall back to the role destination rather than landing on a 403.
- Applies to the page response and the ajax/JSON response alike.

## 2. Access denials journalled

- Every access denied to an **authenticated** user (`AccessDeniedException` on a route, a voter refusing, `access_control`) emits a security event `access.denied` with route, method, the user's roles, the attribute refused, IP, request id, UTC time — no request body, no query string secrets.
- The response stays the application's (`403` page / JSON envelope); this is only the trace.
- Anonymous access to a protected page is a redirect to login, not a denial: not journalled as one.
- Burst protection: the same denial repeated many times per minute must not flood the journal — one entry with a count, or a rate-limited write.

An application "scope" (e.g. which organization the user tried to reach) is passed through the voter's refusal if the application wants it in the entry: offer a way (an exception or voter result carrying extra context), do not guess it.

## Tests

- Login without a saved target lands on the first configured role's route; with a saved but unreachable one, on the role route.
- A voter refusal and an `access_control` refusal each produce one `access.denied` entry with the expected fields.
- An anonymous visit to a protected page produces no denial entry.
- Repeated denials are bounded in the journal.

## Plan

Author: agent:symfony-user

- [x] `post_login.{routes, default_route}`; `PostLoginTargetService` resolves the destination for every sign-in end: password, second factor, reset, terms accepted, magic link (a success handler for `login_link`).
- [x] A saved target path is kept only if `access_control` lets the user in.
- [x] `AccessDeniedJournalSubscriber`: `access.denied` for a fully authenticated user, with route, path, HTTP method, roles, attributes, voter reasons; one entry a minute per user, route and attributes, carrying the count of those held back.
- [x] Tests, docs.

## Work log

- The detail checked against the code: right — every success path redirected to the saved target path or `/`, and nothing journaled denials.
- Waited for the `symfony-security` extraction (`2b55774`) to land: it touched the journal files this needed.
- `PostLoginTargetService` serves every end of a sign-in: the login form, the second factor, a reset or activation (unless a second factor is still to come), the terms accepted, and magic links through `LoginLinkSuccessHandler` — a new `success_handler` the application sets on `login_link` (Symfony's default handler knows no role routes).
- Reachability reads `access_control` only (`security.access_map`, decided on the user's token). A page refused by its controller's `#[IsGranted]` cannot be decided before running it: the user lands on it and gets 403. Said in the docs.
- Scope: Symfony 7.3+ voters give reasons (`Vote::addReason()`), carried by `AccessDeniedException::getAccessDecision()`; journaled as `reasons`. No custom exception needed. Symfony's own RoleVoter gives one too ("The user doesn't have ROLE_X.").
- Not journaled: no token, a remembered-only token (Symfony sends both to the login page), a pending second factor.
- Burst: one entry per user, route and attributes a minute, in `cache.app`; the next carries `suppressed`.
- Tests: `PostLoginTest` (5) on `PostLoginAppKernel`, with a fixture voter; each rule mutation-checked; suite 103 green.
- Not checked in the design-system app: its login answers 500 since the `symfony-security` extraction — `Wexample\SymfonySecurity\Log\AbstractSecretRedactionProcessor` not found while loading symfony-api's processor. Not this change; for the extraction's agent.

## Reply

1. **Real gap, implemented, no demo.** Committed with this file.
2. **What the package does now.**
   - `post_login.routes` (role → route, ordered, default `{}`) and `post_login.default_route` (default `~`: home page). A saved page wins when `access_control` lets the user in.
   - Applies to the page and JSON answers of every sign-in end; magic links need `login_link.success_handler: Wexample\SymfonyUser\Security\Handler\LoginLinkSuccessHandler`.
   - `access.denied` for a fully signed-in user: route, path without query, HTTP method, roles, attributes, voter reasons, plus the journal's user, IP, request id and UTC time. Once a minute per user, route and attributes, with `suppressed`.
3. **Found on the way.** The design-system app is broken by the `symfony-security` extraction (above).
4. **Notice for the application agent.**

> ```yaml
> wexample_symfony_user:
>     post_login:
>         routes:
>             ROLE_HOME_HABILIS_IT: <admin route>
>             ROLE_ADMIN_ESTABLISHMENT: <establishment admin route>
>             ROLE_PDS: <dashboard route>
>         default_route: ~
> security:
>     firewalls:
>         main:
>             login_link:
>                 success_handler: Wexample\SymfonyUser\Security\Handler\LoginLinkSuccessHandler
> ```
> - Reserve each surface in `access_control` by path: that is what decides whether a saved page is kept.
> - To journal the scope of a refusal, have the voter call `$vote->addReason('establishment=42')`; it lands in `extra.reasons`.
> - The 403 page and JSON envelope stay yours.
