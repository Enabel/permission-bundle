<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Matrix;

use Enabel\Permission\Matrix\MatrixRow;
use Enabel\Permission\Matrix\PermissionMatrix;
use Enabel\Permission\Tests\DependencyInjection\PermissionDiscoveryPassTest;
use Enabel\Permission\Tests\Fixtures\Permissions\NewsPermissions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Security\Core\Role\RoleHierarchy;

class PermissionMatrixTest extends TestCase
{
    private const array HIERARCHY = [
        'ROLE_SUPER_ADMIN' => ['ROLE_ADMIN'],
        'ROLE_ADMIN' => ['ROLE_NEWS_EDITOR'],
        'ROLE_NEWS_EDITOR' => ['ROLE_USER'],
        'ROLE_EMPLOYEE' => ['ROLE_USER'],
    ];

    private function matrix(): PermissionMatrix
    {
        return new PermissionMatrix(PermissionDiscoveryPassTest::fixtures(), new RoleHierarchy(self::HIERARCHY), self::HIERARCHY);
    }

    public function testRolesComeFromTheHierarchyAndTheGrantsMostPowerfulFirst(): void
    {
        $this->assertSame(
            ['ROLE_SUPER_ADMIN', 'ROLE_ADMIN', 'ROLE_EMPLOYEE', 'ROLE_NEWS_EDITOR', 'ROLE_EXTERNAL', 'ROLE_USER'],
            $this->matrix()->roles(),
        );
    }

    public function testRowsAreGroupedByDomain(): void
    {
        $domains = $this->matrix()->domains();

        $this->assertSame(['User', 'News'], array_keys($domains));
        $this->assertSame(['NEWS_VIEW', 'NEWS_EDIT', 'NEWS_COMMENT'], array_map(static fn (MatrixRow $row): string => $row->value(), $domains['News']));
    }

    public function testCellsApplyTheHierarchyAndTheExceptRoles(): void
    {
        $comment = $this->row(NewsPermissions::comment);

        $this->assertTrue($comment->roles['ROLE_SUPER_ADMIN'], 'inherits ROLE_NEWS_EDITOR');
        $this->assertTrue($comment->roles['ROLE_EMPLOYEE']);
        $this->assertFalse($comment->roles['ROLE_EXTERNAL']);
        $this->assertFalse($comment->roles['ROLE_USER']);
    }

    public function testRelationsAreListedNotTickedForARole(): void
    {
        $edit = $this->row(NewsPermissions::edit);

        $this->assertSame(['creator'], $edit->relations);
        $this->assertTrue($edit->roles['ROLE_ADMIN']);
        $this->assertFalse($edit->roles['ROLE_NEWS_EDITOR']);
    }

    private function row(NewsPermissions $permission): MatrixRow
    {
        foreach ($this->matrix()->domains()['News'] as $row) {
            if ($permission === $row->permission) {
                return $row;
            }
        }

        $this->fail(\sprintf('No row for %s.', $permission->value));
    }
}
