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
 * Who is granted a permission case.
 *
 * A Grant is held when the user reaches one of its roles through the role
 * hierarchy, or when one of its relations holds for the subject, and the user
 * reaches none of its except roles. Several Grant on a case are alternatives.
 *
 *     #[Grant('ROLE_EMPLOYEE', except: ['ROLE_EXTERNAL'])]
 *     #[Grant(relations: ['creator'])]
 *     case edit = 'NEWS_EDIT';
 */
#[\Attribute(\Attribute::TARGET_CLASS_CONSTANT | \Attribute::IS_REPEATABLE)]
final readonly class Grant
{
    /**
     * @var list<string>
     */
    public array $roles;

    /**
     * @param string|list<string> $roles     roles that are sufficient (OR)
     * @param list<string>        $relations names of the relations that are sufficient (OR)
     * @param list<string>        $except    roles that rule this Grant out, whatever the roles or relations
     */
    public function __construct(
        string|array $roles = [],
        public array $relations = [],
        public array $except = [],
    ) {
        $this->roles = \is_string($roles) ? [$roles] : $roles;
    }
}
