<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Fixtures\Permissions;

use Enabel\Permission\Attribute\Grant;
use Enabel\Permission\Permission;

enum NewsPermissions: string implements Permission
{
    #[Grant('ROLE_NEWS_EDITOR')]
    case view = 'NEWS_VIEW';

    #[Grant('ROLE_ADMIN')]
    #[Grant(relations: ['creator'])]
    case edit = 'NEWS_EDIT';

    #[Grant(['ROLE_EMPLOYEE', 'ROLE_NEWS_EDITOR'], except: ['ROLE_EXTERNAL'])]
    case comment = 'NEWS_COMMENT';
}
