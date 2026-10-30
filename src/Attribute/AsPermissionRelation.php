<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Attribute;

/**
 * Registers a PermissionRelation under the name used in #[Grant(relations: [...])].
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final readonly class AsPermissionRelation
{
    public function __construct(
        public string $name,
    ) {
    }
}
