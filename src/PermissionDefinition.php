<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

use Enabel\Permission\Attribute\Grant;

final readonly class PermissionDefinition
{
    /**
     * @param list<Grant> $grants
     */
    public function __construct(
        public Permission $permission,
        public string $domain,
        public array $grants,
    ) {
    }

    /**
     * Relation names used by any of the grants, in declaration order.
     *
     * @return list<string>
     */
    public function relations(): array
    {
        $relations = [];
        foreach ($this->grants as $grant) {
            foreach ($grant->relations as $relation) {
                $relations[$relation] = true;
            }
        }

        return array_keys($relations);
    }
}
