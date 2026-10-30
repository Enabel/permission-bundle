<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Fixtures\Permissions;

/**
 * Not a Permission: lives next to them and must be ignored.
 */
enum Status: string
{
    case draft = 'ROLE_DRAFT';
}
