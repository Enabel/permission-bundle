<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\DependencyInjection;

use Enabel\Permission\Attribute\Grant;
use Enabel\Permission\Permission;
use Enabel\Permission\PermissionRegistry;
use Symfony\Component\Config\Resource\DirectoryResource;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * Reads the permission enums of the configured directories and compiles them
 * into the PermissionRegistry.
 *
 * A mistake in a declaration fails the container build, so it never reaches
 * production as a permission silently denied to everyone.
 */
final class PermissionDiscoveryPass implements CompilerPassInterface
{
    public const string MAPPINGS_PARAMETER = 'enabel_permission.mappings';
    public const string RELATION_TAG = 'enabel_permission.relation';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(PermissionRegistry::class)) {
            return;
        }

        /** @var array<string, string> $mappings */
        $mappings = $container->getParameterBag()->resolveValue($container->getParameter(self::MAPPINGS_PARAMETER));

        $relations = [];
        foreach ($container->findTaggedServiceIds(self::RELATION_TAG) as $id => $tags) {
            foreach ($tags as $tag) {
                $relations[(string) $tag['name']] = $id;
            }
        }

        $compiled = [];
        foreach ($mappings as $namespace => $directory) {
            if (!is_dir($directory)) {
                continue;
            }

            $container->addResource(new DirectoryResource($directory, '/\.php$/'));

            foreach (self::enumsIn(rtrim($namespace, '\\').'\\', $directory) as $enum) {
                foreach (self::compile($enum, $relations) as $value => $definition) {
                    if (isset($compiled[$value])) {
                        throw new LogicException(\sprintf('The permission "%s" is declared by both %s and %s.', $value, $compiled[$value]['enum'], $enum));
                    }

                    $compiled[$value] = $definition;
                }
            }
        }

        $container->getDefinition(PermissionRegistry::class)->setArgument('$compiled', $compiled);
    }

    /**
     * @return iterable<class-string<Permission>>
     */
    private static function enumsIn(string $namespace, string $directory): iterable
    {
        $files = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS));
        $classes = [];

        /** @var \SplFileInfo $file */
        foreach ($files as $file) {
            if ('php' !== $file->getExtension()) {
                continue;
            }

            $relative = substr($file->getPathname(), \strlen(rtrim($directory, '/')) + 1, -4);
            $classes[] = $namespace.str_replace('/', '\\', $relative);
        }

        // Directory order depends on the filesystem: sort for a stable registry and matrix.
        sort($classes);

        foreach ($classes as $class) {
            if (enum_exists($class) && is_subclass_of($class, Permission::class)) {
                yield $class;
            }
        }
    }

    /**
     * @param class-string<Permission> $enum
     * @param array<string, string>    $relations relation name => service id
     *
     * @return array<string, array{enum: class-string<Permission>, domain: string, grants: list<array{roles: list<string>, relations: list<string>, except: list<string>}>}>
     */
    private static function compile(string $enum, array $relations): array
    {
        $reflection = new \ReflectionEnum($enum);
        if ('string' !== (string) $reflection->getBackingType()) {
            throw new LogicException(\sprintf('The permission enum %s must be backed by strings.', $enum));
        }

        $domain = $reflection->getShortName();
        if (str_ends_with($domain, 'Permissions') && 'Permissions' !== $domain) {
            $domain = substr($domain, 0, -\strlen('Permissions'));
        }

        $compiled = [];
        foreach ($reflection->getCases() as $case) {
            /** @var \ReflectionEnumBackedCase $case */
            $value = (string) $case->getBackingValue();
            $where = \sprintf('%s::%s', $enum, $case->getName());

            if (str_starts_with($value, 'ROLE_')) {
                throw new LogicException(\sprintf('%s: the permission "%s" must not start with ROLE_, Symfony\'s RoleVoter would answer it instead.', $where, $value));
            }

            $grants = [];
            foreach ($case->getAttributes(Grant::class) as $attribute) {
                $grant = $attribute->newInstance();

                if ([] === $grant->roles && [] === $grant->relations) {
                    throw new LogicException(\sprintf('%s: a #[Grant] needs at least one role or relation.', $where));
                }

                foreach ($grant->relations as $relation) {
                    if (!isset($relations[$relation])) {
                        throw new LogicException(\sprintf('%s: no PermissionRelation is registered as "%s" with #[AsPermissionRelation].', $where, $relation));
                    }
                }

                $grants[] = [
                    'roles' => $grant->roles,
                    'relations' => $grant->relations,
                    'except' => $grant->except,
                ];
            }

            if ([] === $grants) {
                throw new LogicException(\sprintf('%s has no #[Grant]: say who is granted, a permission nobody holds is a mistake.', $where));
            }

            $compiled[$value] = ['enum' => $enum, 'domain' => $domain, 'grants' => $grants];
        }

        return $compiled;
    }
}
