<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Fixtures\Relation;

use Enabel\Permission\Attribute\AsPermissionRelation;
use Enabel\Permission\PermissionRelation;
use Enabel\Permission\Tests\Fixtures\Article;
use Symfony\Component\Security\Core\User\UserInterface;

#[AsPermissionRelation('creator')]
final class CreatorRelation implements PermissionRelation
{
    public function holds(UserInterface $user, mixed $subject): bool
    {
        return $subject instanceof Article && $subject->createdBy === $user->getUserIdentifier();
    }
}
