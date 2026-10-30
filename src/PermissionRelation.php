<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

use Symfony\Component\Security\Core\User\UserInterface;

/**
 * A relation between the user and the subject that grants a permission
 * (creator, owner, member of the team...), named with #[AsPermissionRelation].
 *
 * Only asked when a subject is given: without one, there is nothing to relate to.
 */
interface PermissionRelation
{
    public function holds(UserInterface $user, mixed $subject): bool;
}
