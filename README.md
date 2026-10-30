# Enabel Permission for Symfony

[![CI](https://github.com/Enabel/permission-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/Enabel/permission-bundle/actions/workflows/ci.yml)

Permission enums for Symfony applications: who is granted each permission is declared on the enum
case itself, one voter answers them all, and a matrix shows who can do what.

```php
enum NewsPermissions: string implements Permission
{
    #[Grant('ROLE_NEWS_EDITOR')]
    case view = 'NEWS_VIEW';

    // An editor manages their own news, an admin all of them.
    #[Grant('ROLE_ADMIN')]
    #[Grant(relations: ['creator'])]
    case edit = 'NEWS_EDIT';

    // Every employee, but not the external staff.
    #[Grant('ROLE_EMPLOYEE', except: ['ROLE_EXTERNAL'])]
    case comment = 'NEWS_COMMENT';
}
```

```php
#[IsGranted(NewsPermissions::edit->value, subject: 'news')]
```

```twig
{% if is_granted(enum('App\\Security\\Permissions\\NewsPermissions').edit, news) %}
```

## Documentation

Read the [documentation](docs/index.md): the model, relations, rules, the permission matrix and
the migration from an in-house permission map.

## Installation

```bash
composer require enabel/permission-bundle
```

## License

MIT License (MIT): see the [License File](LICENSE) for more details.
