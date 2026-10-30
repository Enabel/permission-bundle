<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Fixtures\Invalid\EmptyGrant;

use Enabel\Permission\Attribute\Grant;
use Enabel\Permission\Permission;

enum ReportPermissions: string implements Permission
{
    #[Grant(except: ['ROLE_EXTERNAL'])]
    case view = 'REPORT_VIEW';
}
