<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

/**
 * A permission enum: one string-backed enum per domain, each case declaring
 * who is granted with #[Grant].
 *
 * The value is the attribute checked by is_granted() (e.g. NEWS_EDIT). It must
 * not start with ROLE_: Symfony's RoleVoter would answer it instead.
 */
interface Permission extends \BackedEnum
{
}
