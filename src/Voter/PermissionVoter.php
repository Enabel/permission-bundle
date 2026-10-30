<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Voter;

use Enabel\Permission\Permission;
use Enabel\Permission\PermissionDefinition;
use Enabel\Permission\PermissionRegistry;
use Enabel\Permission\PermissionRelation;
use Enabel\Permission\PermissionRule;
use Psr\Container\ContainerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\Role\RoleHierarchyInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Answers every permission of the registry: granted when one of its Grant
 * holds (roles through the hierarchy, or a relation to the subject, without
 * any except role), then only if no PermissionRule restricts it.
 *
 * Accepts the enum case as well as its value: is_granted(NewsPermissions::edit).
 *
 * @extends Voter<string, mixed>
 */
final class PermissionVoter extends Voter
{
    /**
     * @param ContainerInterface       $relations relation name => PermissionRelation
     * @param iterable<PermissionRule> $rules
     */
    public function __construct(
        private readonly PermissionRegistry $registry,
        private readonly RoleHierarchyInterface $roleHierarchy,
        private readonly ContainerInterface $relations,
        private readonly iterable $rules = [],
    ) {
    }

    public function vote(TokenInterface $token, mixed $subject, array $attributes, ?Vote $vote = null): int
    {
        $attributes = array_map(
            static fn (mixed $attribute): mixed => $attribute instanceof Permission ? $attribute->value : $attribute,
            $attributes,
        );

        return parent::vote($token, $subject, $attributes, $vote);
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $this->registry->has($attribute);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $definition = $this->registry->get($attribute);

        if (!$this->isGranted($definition, $subject, $token, $vote)) {
            return false;
        }

        foreach ($this->rules as $rule) {
            if ($rule->supports($definition->permission, $subject) && !$rule->allows($definition->permission, $subject, $token)) {
                $vote?->addReason(\sprintf('Restricted by %s.', $rule::class));

                return false;
            }
        }

        return true;
    }

    private function isGranted(PermissionDefinition $definition, mixed $subject, TokenInterface $token, ?Vote $vote): bool
    {
        $reachable = $this->roleHierarchy->getReachableRoleNames($token->getRoleNames());
        $user = $token->getUser();

        foreach ($definition->grants as $grant) {
            $excluded = array_values(array_intersect($grant->except, $reachable));
            if ([] !== $excluded) {
                $vote?->addReason(\sprintf('A grant excludes %s.', implode(', ', $excluded)));
                continue;
            }

            $roles = array_values(array_intersect($grant->roles, $reachable));
            if ([] !== $roles) {
                $vote?->addReason(\sprintf('Granted by the role %s.', $roles[0]));

                return true;
            }

            if (null === $subject || !$user instanceof UserInterface) {
                continue;
            }

            foreach ($grant->relations as $name) {
                /** @var PermissionRelation $relation */
                $relation = $this->relations->get($name);

                if ($relation->holds($user, $subject)) {
                    $vote?->addReason(\sprintf('Granted by the relation "%s".', $name));

                    return true;
                }
            }
        }

        return false;
    }
}
