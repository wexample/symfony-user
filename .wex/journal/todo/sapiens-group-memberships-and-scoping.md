# Sapiens — group memberships, roles derived from them, and data scoped to them

Opened: 2026-10-01
Updated: 2026-10-08
Author: agent:sapiens

## State (2026-10-08, agent:main)

Sections 1 to 5 are built in symfony-user, tested (`tests/Integration/MembershipTest.php`, `tests/Unit/MembershipsConfigurationTest.php`) and documented (usage, "Memberships"). What differs from the plan below:

- Names: `MembershipGroupInterface`, `MemberInterface`, `MembershipInterface`, `RepresentativeInterface` (an account acting for its organizations), `Entity/Traits/HasGrantsTrait`. No account-side trait: `MembershipService` (`getGrants`, `holds`, `isMember`, `getGroups`, `getMembership`) works on any member.
- Section 2: the roles a grant gives come from a voter (`Security/Voter/MembershipRoleVoter`), read at each request. A membership changed applies at the next request **without** signing out (network #271, and `AbstractUser::isEqualTo` already leaves roles out). `getRoles()` does not hold them: the 2FA policy and the landing page after login do not see them. The "a platform role excludes memberships" rule is not built.
- Section 3: `MembershipScopeService::restrict()`, `restrictToCurrentMember()`, `assertInScope()` (404). Ids are bound with their column type (binary uuid).
- Section 4: `Security/Guard/MembershipAdministrationGuard`, on `memberships.administer_grant`. An actor holding it nowhere keeps the `administration` rules; an account in no group is out of reach, except while being created.

Then, the same day:
- symfony-user: `MembershipManagerInterface` (one per kind of group, the app's), `MembershipAdministrationService` (add, change grants, remove; `last_administrator` kept; journaled `membership.*`).
- symfony-user-ds: `/memberships/{kind}:{id}` (members, read by members, changed by administrators), the member modal (grants, leaving), the add form with symfony-search's entity field (the member class carries `#[Searchable]`), `memberships_url()`, "Mes groupes" on the profile. Tested in `tests/Integration/MembershipScreenTest.php` (access and forms; the pages are not rendered in the fixture kernel).
- symfony-user-demo: `DemoTeam`, `DemoTeamMembership`, `/user/teams`; demo administers Rédaction, watches Studio. Walked in Chrome in MOJOE design-system: add by search, grants in the modal, removal, read-only team, profile.

Left:
- A change made in the modal reloads the whole members page (`redirect`): the loader cannot refresh a page under a modal once its form succeeded. To build in symfony-loader, then use here.
- Grants are shown by their raw name (`Administrator`): no translation of the application's grants yet.
- Sapiens' migration below.

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

## 5. What network's projects add (2026-10-08, with the owner)

The `symfony-project` proposal from the network archaeology is dropped: past
its membership, a project is network's own business (invoices, contracts,
organizations), and stays an entity of the app. What it shares with every
application is this todo's machinery, with two more needs:

- **The member is not always an account.** In network the member of a project is
  an organization (#36, #237). The member side is an interface too — an account,
  an organization, anything — and the group side stays `GroupInterface`, so that
  any entity can be joined: a project, a workspace, a shared document.
- **Grants that include one another.** network's levels, in use for years:
  administrator ⊃ contributor ⊃ watcher, and client ⊃ watcher. A configurable
  hierarchy of grants, and a voter answering "holds at least this grant on this
  group" — `is_granted('contributor', $project)`.
- Changing someone's grants must not sign them out (#271): weigh this against
  section 2, which ends the session when roles change.

Sources (read only, network runs on production data):
`NETWORK/archeo/proposed-packages/dismiss/symfony-project/todo/extract-from-network.md`
and the knowledge page it points to (`archeology/project.md.j2`, permission matrix).

## Tests

- An account with no membership sees nothing, and an empty scope never widens to everything.
- An account in two groups sees the records of both, and nothing of a third.
- A record of another group answers 404.
- Roles follow memberships, and a change of membership ends the session at the next request.
- An administrator of group A cannot administer an account that also belongs to group B.

## Then, in Sapiens

`Establishment` and `EstablishmentMembership` implement the interfaces, with the `care` and `administer` grants. The files listed above are deleted, and `PatientIsolationTest` and `RoleAccessTest` must stay green without being modified.
