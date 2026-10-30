<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Matrix;

use Enabel\Permission\Permission;

final readonly class MatrixRow
{
    /**
     * @param array<string, bool> $roles     role => granted to a user holding only this role
     * @param list<string>        $relations relations that also grant it, for the subject they hold on
     */
    public function __construct(
        public Permission $permission,
        public array $roles,
        public array $relations,
    ) {
    }

    public function value(): string
    {
        return (string) $this->permission->value;
    }

    public function name(): string
    {
        return $this->permission->name;
    }
}
