# Enabel Permission

## Why

Travel, the Information Portal and Impala each grew their own permission layer: string-backed
enums per domain, a map from each permission to the roles that are sufficient, a voter resolving
the role hierarchy, and a page showing the matrix. Each then added what it needed: ownership in
the Information Portal, excluded roles in Impala, checks on the subject (a super admin is only
managed by a super admin) in Travel and Impala. EAgle grants access by relation to the subject
(creator of a record, owner of an action).

This bundle is that common core, with explicit extension points instead of a voter per app.

## The model

A permission is granted when **one of its `#[Grant]` holds**, then **only if no rule restricts it**.

A `#[Grant]` holds when the user:

- reaches one of its **roles** through the role hierarchy, **or** one of its **relations** holds
  for the subject,
- **and** reaches none of its **except** roles.

```text
granted = (Grant₁ or Grant₂ or …) and Rule₁ and Rule₂ and …
Grant   = (roles ∪ relations) − except
```

Only grants grant; rules only restrict. A decision is read on the enum case, plus the rules that
apply to it.

## Declaring permissions

One string-backed enum per domain, implementing `Enabel\Permission\Permission`, in
`src/Security/Permissions/` (see [configuration](#configuration)):

```php
namespace App\Security\Permissions;

use Enabel\Permission\Attribute\Grant;
use Enabel\Permission\Permission;

enum ProjectPermissions: string implements Permission
{
    #[Grant('ROLE_USER')]
    case view = 'PROJECT_VIEW';

    #[Grant(['ROLE_FOCAL_POINT', 'ROLE_MEL_OFFICER'])]
    case edit_location = 'PROJECT_EDIT_LOCATION';

    // Administrators and validators, except the external staff among them.
    #[Grant(['ROLE_ADMIN', 'ROLE_LOGFRAME_VALIDATOR'], except: ['ROLE_EXTERNAL'])]
    case validate_result_framework = 'PROJECT_VALIDATE_RESULT_FRAMEWORK';
}
```

- `roles`: a role or a list of roles, any of them is enough. Through the role hierarchy, a role
  above them is granted too.
- `relations`: names of [relations](#relations), any of them is enough, for the subject passed to
  `is_granted()`.
- `except`: roles ruling this Grant out, through the hierarchy too. They only rule out the
  Grant they are on: another Grant of the same case can still grant.
- Several `#[Grant]` on a case are alternatives.

Write the reason of a choice as a comment above the case: it is what a security review reads.

### What fails the build

The container does not compile, so the mistake never reaches production as a permission silently
denied to everyone, when:

- a case has no `#[Grant]`,
- a `#[Grant]` has neither role nor relation,
- a relation name is not registered,
- a value starts with `ROLE_` (Symfony's `RoleVoter` would answer it instead of the bundle),
- two cases share a value,
- a permission enum is not backed by strings.

### Checking a permission

The enum case and its value both work:

```php
$security->isGranted(ProjectPermissions::view);
$security->isGranted(ProjectPermissions::edit_location, $project);
```

```php
// Attributes take a constant expression: the value.
#[IsGranted(ProjectPermissions::view->value)]
#[IsGranted(ProjectPermissions::edit_location->value, subject: 'project')]
```

```twig
{% set ProjectPermissions = enum('App\\Security\\Permissions\\ProjectPermissions') %}
{% if is_granted(ProjectPermissions.edit_location, project) %}…{% endif %}
```

A missing subject is fine for roles; relations are only asked when a subject is given.

## Relations

A relation grants a permission to the users related to the subject: its creator, the owners of an
action, the members of a team. It is a service implementing `PermissionRelation`, named with
`#[AsPermissionRelation]` (autoconfigured):

```php
use Enabel\Permission\Attribute\AsPermissionRelation;
use Enabel\Permission\PermissionRelation;

#[AsPermissionRelation('creator')]
final class CreatorRelation implements PermissionRelation
{
    public function holds(UserInterface $user, mixed $subject): bool
    {
        // Gedmo Blameable stores the user identifier, not the User.
        return $subject instanceof BlameableInterface
            && $subject->getCreatedBy() === $user->getUserIdentifier();
    }
}
```

```php
#[Grant('ROLE_MANAGER')]
#[Grant(relations: ['creator', 'action_owner'])]
case edit = 'RECOMMENDATION_EDIT';
```

A relation is a service: it can use a repository (the employee linked to the user, the team of a
project…). It is only built when a Grant names it and no role already granted.

## Rules

A rule adds a condition to a permission that is already granted. It implements `PermissionRule`
(autoconfigured) and can only restrict:

```php
final class OnlySuperAdminsManageSuperAdmins implements PermissionRule
{
    public function __construct(private RoleHierarchyInterface $roleHierarchy)
    {
    }

    public function supports(Permission $permission, mixed $subject): bool
    {
        return \in_array($permission, [UserPermissions::edit, UserPermissions::impersonate], true)
            && $subject instanceof User;
    }

    public function allows(Permission $permission, mixed $subject, TokenInterface $token): bool
    {
        return !\in_array('ROLE_SUPER_ADMIN', $subject->getRoles(), true)
            || \in_array('ROLE_SUPER_ADMIN', $this->roleHierarchy->getReachableRoleNames($token->getRoleNames()), true);
    }
}
```

Use a rule for what depends on the subject and cannot be a role: a super admin only managed by a
super admin, nobody impersonating themselves, a closed record nobody edits. Not to grant: that is
the job of a Grant.

## The permission matrix

`PermissionMatrix` lists every permission against every role, with the hierarchy and the except
roles applied, and the relations that also grant it. The route and the permission guarding the
page stay in the application:

```php
#[Route('/admin/permissions', name: 'admin_permissions')]
#[IsGranted(AdminPermissions::permissions->value)]
final class PermissionsController extends AbstractController
{
    public function __invoke(PermissionMatrix $matrix): Response
    {
        return $this->render('admin/permissions.html.twig', ['matrix' => $matrix]);
    }
}
```

```twig
{% include '@EnabelPermission/matrix.html.twig' with {matrix: matrix} %}

{# or, to label the permissions: #}
{% embed '@EnabelPermission/matrix.html.twig' with {matrix: matrix} %}
    {% block permission_label %}{{ ('permission.' ~ row.value) | trans }}{% endblock %}
{% endembed %}
```

The template uses Bootstrap classes and the `enabel_permission` translation domain (en, fr, nl).
Relations are labelled with `relation.<name>` in that domain when the application translates
them, by their name otherwise.

Domains are named after the enum, without its `Permissions` suffix (`ProjectPermissions` →
`Project`). Roles are the ones of the grants and of the hierarchy, the most powerful first.

## debug:permission

Lists every permission and who is granted it, as declared:

```bash
bin/console debug:permission
bin/console debug:permission project
```

## Configuration

```yaml
# config/packages/enabel_permission.yaml
enabel_permission:
    # Namespace prefix => directory of the permission enums, read recursively.
    # Default:
    mappings:
        App\Security\Permissions\: '%kernel.project_dir%/src/Security/Permissions'
```

Enums are read when the container is compiled; in dev, adding or changing one rebuilds it.

## Migrating an in-house permission layer

1. Make each enum implement `Enabel\Permission\Permission` and move its roles from the map to a
   `#[Grant]` on each case, with the comments explaining them.
2. Rename the values starting with `ROLE_` (Impala: `ROLE_EA_ACCESS`, `ROLE_ALLOWED_TO_SWITCH`).
3. Ownership (Information Portal `OwnableInterface`): a relation, e.g. `owner`.
4. Excluded roles (Impala `$permissionsDeniedRolesMap`): `except` on the Grant they restrict.
5. Checks on the subject inside a voter or a policy (super admin protection, no self
   impersonation): rules.
6. Remove the app voter, the map, the discovery pass and the "every case is mapped" test: the
   build now enforces it. Render the matrix page with `PermissionMatrix`.
