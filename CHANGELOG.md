# Changelog

## [v1.0.1](https://github.com/Enabel/permission-bundle/tree/v1.0.1) (2026-10-30)

[Full Changelog](https://github.com/Enabel/permission-bundle/compare/v1.0.0...v1.0.1)

**Fixed bugs:**

- The permission matrix rendered one table per domain, each sizing its own columns: a role's column moved from one domain to the next. It is now one table: each domain opens with its name and the roles, so a column keeps its role from the first domain to the last and the roles stay in sight; every other role column is shaded. **Markup change** for applications that override `@EnabelPermission/matrix.html.twig`: one `<table>` without `<thead>`, a `<tbody data-domain>` per domain whose first row holds the roles.

## [v1.0.0](https://github.com/Enabel/permission-bundle/tree/v1.0.0) (2026-10-30)

**Implemented enhancements:**

- Permission enums declaring who is granted each case with `#[Grant(roles, relations, except)]`, read when the container is compiled; a declaration mistake fails the build
- `PermissionVoter` answering every permission, the enum case as well as its value, with the role hierarchy and decision reasons for the profiler
- Named relations granting a permission for a subject (`PermissionRelation`, `#[AsPermissionRelation]`)
- Rules restricting a granted permission (`PermissionRule`)
- `PermissionMatrix` and the `@EnabelPermission/matrix.html.twig` template (en, fr, nl)
- `debug:permission` command
