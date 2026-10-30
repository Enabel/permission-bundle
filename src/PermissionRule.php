<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

/**
 * An extra condition on a permission already granted by its roles or
 * relations (a super admin is only managed by a super admin, nobody
 * impersonates themselves...).
 *
 * A rule only restricts: it is asked after a Grant matched, and can never
 * grant what the Grant did not. Services implementing it are picked up
 * automatically.
 */
interface PermissionRule
{
    public function supports(Permission $permission, mixed $subject): bool;

    public function allows(Permission $permission, mixed $subject, TokenInterface $token): bool;
}
