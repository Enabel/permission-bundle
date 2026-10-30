<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Fixtures\Rule;

use Enabel\Permission\Permission;
use Enabel\Permission\PermissionRule;
use Enabel\Permission\Tests\Fixtures\Permissions\Admin\UserPermissions;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;

final class NotYourselfRule implements PermissionRule
{
    public function supports(Permission $permission, mixed $subject): bool
    {
        return UserPermissions::impersonate === $permission && $subject instanceof UserInterface;
    }

    public function allows(Permission $permission, mixed $subject, TokenInterface $token): bool
    {
        \assert($subject instanceof UserInterface);

        return $subject->getUserIdentifier() !== $token->getUserIdentifier();
    }
}
