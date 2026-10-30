<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

use Enabel\Permission\Attribute\Grant;

/**
 * Every permission of the application, read from the enums when the
 * container is compiled: nothing is reflected at runtime.
 */
final class PermissionRegistry
{
    /**
     * @var array<string, PermissionDefinition>|null
     */
    private ?array $definitions = null;

    /**
     * @param array<string, array{enum: class-string<Permission>, domain: string, grants: list<array{roles: list<string>, relations: list<string>, except: list<string>}>}> $compiled permission value => definition, built by PermissionDiscoveryPass
     */
    public function __construct(
        private readonly array $compiled,
    ) {
    }

    public function has(string $permission): bool
    {
        return isset($this->compiled[$permission]);
    }

    public function get(string $permission): PermissionDefinition
    {
        return $this->all()[$permission] ?? throw new \InvalidArgumentException(\sprintf('Unknown permission "%s".', $permission));
    }

    /**
     * @return array<string, PermissionDefinition> permission value => definition, grouped by domain
     */
    public function all(): array
    {
        if (null !== $this->definitions) {
            return $this->definitions;
        }

        $this->definitions = [];
        foreach ($this->compiled as $value => $definition) {
            $this->definitions[$value] = new PermissionDefinition(
                $definition['enum']::from($value),
                $definition['domain'],
                array_map(
                    static fn (array $grant): Grant => new Grant($grant['roles'], $grant['relations'], $grant['except']),
                    $definition['grants'],
                ),
            );
        }

        return $this->definitions;
    }
}
