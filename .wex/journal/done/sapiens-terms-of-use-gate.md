# Sapiens — terms-of-use gate, versioned, with re-acceptance

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`), requirement SF1-A19 of its specifications. Closes two lines of Sapiens' `.wex/knowledge/contributing/stack-requirements.md.j2`: **Blocking terms-of-use gate on first login: versioned, timestamped UTC, recorded per user** and **A new terms version triggers re-acceptance at the next login**. Nothing in the package handles terms or consent today.

## What the lines ask

An authenticated user reaches nothing until they have accepted the **current** version of the terms of use. Each acceptance is recorded as proof — who, which version, when (UTC). A new version asks again, at the next login or even within a live session.

## A gate, not a checkbox on the login form

Sapiens' wireframe shows an "I have read the terms" checkbox on the login screen. Do **not** put it there:

- the login form answers before the user is known, while acceptance belongs to one account;
- showing the box only to those who have not accepted reveals an account's state before authentication — the oracle closed by `reveal_account_status`;
- a box ticked with a wrong password proves nothing.

The gate sits **after complete authentication, second factor included**: a dedicated page, and nothing else reachable until it is validated. Same shape as the forced app enrolment (`TotpSetupHoldSubscriber`) — reuse that mechanism rather than writing a second one. Order of the two gates to fix; enrolment first, terms second seems right.

## Version

- Declared by the application in configuration, with the path or route of the text: the text belongs to the app.
- The user must have accepted **this** version, not "a" version.
- Changing the version in configuration asks everyone again, with no data migration.

## Proof

- A separate acceptance entity, not a column on `User`: user, version, `acceptedAt` UTC, IP, user agent.
- Kept for **every** version accepted: one must be able to prove who had accepted what on a given date.
- Never updated nor deleted by the application, including when an account is disabled.
- Emits a `SecurityEvent` (`terms.accepted`, with the version) so the journal traces it.

## At the edges

- Ajax requests held by the gate get a `403` JSON, as for forced enrolment.
- Applies to magic-link logins and logins after a reset too, as `two_factor.required` does.
- The gate page shows the text or links to it, and offers an explicit accept action. Refusing logs out. No third way.
- Impersonation does not accept on behalf of the impersonated user.
- A version published while a user is signed in holds them at their next request, without waiting for a new login. If that is judged too strict, make it configurable — a decision, not an oversight.

## Left to Sapiens

The text of the terms, its version, and the privacy policy link in the footer.

## Tests

- A user with no acceptance is held on every protected route, page and ajax.
- Accepting releases them and records version, UTC time, IP.
- Raising the version in configuration holds a user who had accepted the previous one.
- The acceptance history is intact after a new acceptance.
- A forged POST accepting a version other than the current one is refused.

## Work log

Author: agent:symfony-user

- The detail checked against the code: right on every point. Nothing handled terms; `TotpSetupHoldSubscriber` was the mechanism to reuse; a checkbox on the login form would have been an account-state oracle.
- `TotpSetupHoldSubscriber` generalised into `AccountGateInterface` + `AccountGateSubscriber`; gates `TotpSetupGate` (priority 20) then `TermsGate` (10). The tag is registered in the extension: `#[AutoconfigureTag]` on the interface was ignored.
- Gates now skip a `TwoFactorToken`: the hold ran while a password waited for its second factor (scheb's token carries the user), sending public pages — the demo mailbox — to the gate.
- `TermsAcceptance`: unique `(user_id, version)`, no foreign key (user id + identifier, the proof outlives the account); `TermsAcceptanceGuardSubscriber` throws on update and removal.
- `TermsService::mustAccept` caches in session under `identifier@version`; `accept()` takes the current version only and journals `terms.accepted`.
- Mid-session version bump kept strict, no option: no application needs it looser yet.
- Tests: `TermsAppKernel` + `config/terms.yaml`; `TermsGateTest`, 8 tests, each mutation-checked; suite 78 green.
- Demo: the design-system app runs terms `demo-1` (hand-written migration: its schema diff carries other packages' tables). Walked over HTTP, not in a browser.
- Left out of the commits: the design-system app's pending translation changes and untracked files, which belong to other agents.
- Process slips, owned after rereading the protocol: the request was not taken up with `todo_write` at the start, the work log first went to `extract-from-network.md`, and this file was committed after the work instead of with it.

## Reply

1. **Real gap, implemented, with a demo.** Commits: `1b2a709` (symfony-user), `3d010c6` (symfony-user-demo), `53f4887` (design-system app).
2. **What the package does now.**
   - Account gates: `AccountGateInterface`, run by one subscriber after complete authentication, second factor included. App enrolment first (`TotpSetupGate`, which replaces `TotpSetupHoldSubscriber`), terms second (`TermsGate`). Logout and `_`-prefixed routes stay reachable; ajax gets a `403` JSON.
   - `wexample_symfony_user.terms.version` (default `~`: no gate) and `terms.text_route` (the app's page, readable while held).
   - Proof: entity `TermsAcceptance`, table `user_terms_acceptance`, one row per user and version (user id, identifier, version, `acceptedAt` UTC, IP, user agent). No foreign key, so it outlives the account; an update or delete throws. Emits `terms.accepted` with the version.
   - A new version holds everyone at their next request, mid-session included — kept as the default, no option.
   - A forged or outdated version is refused. An impersonator is not held and cannot accept.
   - Every sign-in path: password, magic link, after a reset.
   - Tests: `TermsGateTest` (8 tests, each mutation-checked). Checked over HTTP in the design-system app, not in a browser.
3. **Found and fixed on the way.**
   - Gates (the enrolment hold included) ran while the second factor was pending, redirecting pages that are public.
   - `#[AutoconfigureTag]` on the interface was ignored; the tag is now registered in the extension.
4. **Notice for the application agent.**

> ```yaml
> wexample_symfony_user:
>     terms:
>         version: '<your version>'
>         text_route: <the route of your terms page>
> ```
> - Add a migration for `user_terms_acceptance` (`doctrine:migrations:diff`).
> - The text, its page and the footer privacy link are yours. The route given as `text_route` stays reachable while a user is held.
> - Raising `version` holds every signed-in user at their next request; the history of earlier acceptances is kept.
> - The gate page is `/account/terms` (route `user_terms_index`): accept, or log out.
