# Sapiens — group memberships, roles derived from them, and data scoped to them

Opened: 2026-10-01
Updated: 2026-10-01
Author: agent:sapiens

## Context

Asked by the Sapiens app (`HOME_HABILIS/local/sapiens`). A Sapiens account belongs to one or several establishments, with its own rights in each: a carer in one, a carer and administrator in another. What it may see and administer follows from those memberships. Any B2B application has the same shape: an account inside one or several groups (company, team, site, clinic), with a role in each.

The package must not know what the group is. It provides interfaces and the machinery, and the application implements them on its own entities, the way it extends `AbstractUser` today. In Sapiens the group is `Establishment`, the membership `EstablishmentMembership`, with two flags (`healthcareProfessional`, `administrator`).

Sapiens' code, to be replaced by this once it exists:
- `src/Repository/Traits/EstablishmentScopedRepositoryTrait.php`;
- `src/Service/AccountRolesService.php`;
- the membership helpers of `src/Entity/User.php` (`getCareEstablishments`, `getAdministeredEstablishments`, `getEstablishments`);
- `src/Security/EstablishmentAccountGuard.php`;
- the "out of scope → 404" of `src/Service/FormDataResolver/*`.

It is a small amount of code, but it is written the same way in every application, and a mistake in it leaks data across groups.

## 1. Memberships

- A `GroupInterface` (or a better name) for the application's group entity, with nothing required beyond an id and a label.
- A `MembershipInterface`: an account, a group, and the **grants** held in that group. A grant is a string the application defines, such as `care` or `administer`, and a membership holds any number of them.
- On the account side, a trait offering `getMemberships()`, `getGroups(?string $grant = null)` (the groups where the account holds that grant, or all of them) and `getMembership(GroupInterface $group)`.
- An account may hold no membership at all. In Sapiens, a platform role like `HOME_HABILIS_IT` holds its role directly.

## 2. Global roles derived from memberships

- Configuration mapping each grant to a Symfony role, for example `care: ROLE_PDS` and `administer: ROLE_ADMIN_ESTABLISHMENT`. The roles of an account are then the union of what its memberships grant, so that `access_control` and `is_granted` keep working on plain roles.
- A role held directly, such as a platform role, is kept and may exclude memberships. That fits the existing `exclusive_roles`.
- A change of memberships changes the roles. The session ends at the next request, as Sapiens does with `isEqualTo` comparing roles. That belongs in `AbstractUser` anyway, even without memberships.

## 3. Data scoped to the groups of the account

- A single helper that adds the group condition to a query: `restrict(QueryBuilder $qb, string $groupPath, array $groups)`. **An empty scope matches nothing, never everything.** That is the one rule it exists for.
- A shortcut from the current account and a grant: the groups where the signed-in account holds `care`, applied to the query.
- A record outside the scope answers **404, not 403**: saying "forbidden" confirms that the record exists. Provide it as a value resolver or as a repository helper (`findOneInScopeOrFail`), whichever fits the package better.
- A Doctrine SQL filter applied globally is tempting, but it is invisible at the call site and easy to disable by accident. Prefer explicit scoping, or document clearly why not.

## 4. Administration within groups

- The existing `AccountAdministrationGuardInterface` gains a ready-made implementation. An account may administer another one only if **every** group of the target is a group where it holds the administrating grant. Otherwise, deactivating an account shared with another group would cut that group's access too.
- The grant that means "administers this group" is configurable. Platform roles (`administration.manages`) keep working as today.

## Tests

- An account with no membership sees nothing, and an empty scope never widens to everything.
- An account in two groups sees the records of both, and nothing of a third.
- A record of another group answers 404.
- Roles follow memberships, and a change of membership ends the session at the next request.
- An administrator of group A cannot administer an account that also belongs to group B.

## Then, in Sapiens

`Establishment` and `EstablishmentMembership` implement the interfaces, with the `care` and `administer` grants. The files listed above are deleted, and `PatientIsolationTest` and `RoleAccessTest` must stay green without being modified.
