<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Matrix;

use Enabel\Permission\PermissionRegistry;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;

/**
 * Who can do what: every permission against every role, with the role
 * hierarchy and the except roles applied, plus the relations that also grant
 * it. Shown next to an audit trail, it answers "who could" where the trail
 * answers "who did".
 */
final class PermissionMatrix
{
    /**
     * @var list<string>|null
     */
    private ?array $roles = null;

    /**
     * @param array<string, list<string>> $hierarchy the security.role_hierarchy.roles parameter
     */
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly array $hierarchy = [],
    ) {
    }

    /**
     * Roles named by the grants or the hierarchy, the most powerful first
     * (the one reaching the most roles), then by name.
     *
     * @return list<string>
     */
    public function roles(): array
    {
        if (null !== $this->roles) {
            return $this->roles;
        }

        $roles = [];
        foreach ($this->hierarchy as $role => $children) {
            $roles[$role] = true;
            foreach ($children as $child) {
                $roles[$child] = true;
            }
        }

        foreach ($this->registry->all() as $definition) {
            foreach ($definition->grants as $grant) {
                foreach ([...$grant->roles, ...$grant->except] as $role) {
                    $roles[$role] = true;
                }
            }
        }

        $reach = [];
        foreach (array_keys($roles) as $role) {
            $reach[$role] = \count($this->roleHierarchy->getReachableRoleNames([$role]));
        }

        $sorted = array_keys($roles);
        usort($sorted, static fn (string $a, string $b): int => [$reach[$b], $a] <=> [$reach[$a], $b]);

        return $this->roles = $sorted;
    }

    /**
     * @return array<string, list<MatrixRow>> domain => rows, in the registry order
     */
    public function domains(): array
    {
        $domains = [];
        foreach ($this->registry->all() as $definition) {
            $granted = [];
            foreach ($this->roles() as $role) {
                $granted[$role] = $this->grantedTo($role, $definition->grants);
            }

            $domains[$definition->domain][] = new MatrixRow($definition->permission, $granted, $definition->relations());
        }

        return $domains;
    }

    /**
     * @param list<\Enabel\Permission\Attribute\Grant> $grants
     */
    private function grantedTo(string $role, array $grants): bool
    {
        $reachable = $this->roleHierarchy->getReachableRoleNames([$role]);

        foreach ($grants as $grant) {
            if ([] === array_intersect($grant->except, $reachable) && [] !== array_intersect($grant->roles, $reachable)) {
                return true;
            }
        }

        return false;
    }
}
