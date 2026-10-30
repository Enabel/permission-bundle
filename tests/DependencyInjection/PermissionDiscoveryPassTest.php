<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\DependencyInjection;

use Enabel\Permission\DependencyInjection\PermissionDiscoveryPass;
use Enabel\Permission\PermissionRegistry;
use Enabel\Permission\Tests\Fixtures\Permissions\Admin\UserPermissions;
use Enabel\Permission\Tests\Fixtures\Permissions\NewsPermissions;
use Enabel\Permission\Tests\Fixtures\Relation\CreatorRelation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

class PermissionDiscoveryPassTest extends TestCase
{
    /**
     * Runs the pass on the fixtures of one directory and returns the registry it compiled.
     */
    public static function registryFor(string $namespace, string $directory): PermissionRegistry
    {
        $container = new ContainerBuilder();
        $container->setParameter(PermissionDiscoveryPass::MAPPINGS_PARAMETER, [$namespace => $directory]);
        $container->register(PermissionRegistry::class)->setArgument('$compiled', []);
        $container->register(CreatorRelation::class)->addTag(PermissionDiscoveryPass::RELATION_TAG, ['name' => 'creator']);

        (new PermissionDiscoveryPass())->process($container);

        /** @var array<string, array{enum: class-string<\Enabel\Permission\Permission>, domain: string, grants: list<array{roles: list<string>, relations: list<string>, except: list<string>}>}> $compiled */
        $compiled = $container->getDefinition(PermissionRegistry::class)->getArgument('$compiled');

        return new PermissionRegistry($compiled);
    }

    public static function fixtures(): PermissionRegistry
    {
        return self::registryFor('Enabel\\Permission\\Tests\\Fixtures\\Permissions\\', \dirname(__DIR__).'/Fixtures/Permissions');
    }

    public function testEnumsAreFoundRecursivelyAndOtherEnumsIgnored(): void
    {
        $registry = self::fixtures();

        $this->assertSame(
            ['USER_IMPERSONATE', 'NEWS_VIEW', 'NEWS_EDIT', 'NEWS_COMMENT'],
            array_keys($registry->all()),
        );
        $this->assertFalse($registry->has('ROLE_DRAFT'));
    }

    public function testDefinitionsKeepTheCaseTheDomainAndTheGrants(): void
    {
        $edit = self::fixtures()->get('NEWS_EDIT');

        $this->assertSame(NewsPermissions::edit, $edit->permission);
        $this->assertSame('News', $edit->domain);
        $this->assertCount(2, $edit->grants);
        $this->assertSame(['ROLE_ADMIN'], $edit->grants[0]->roles);
        $this->assertSame(['creator'], $edit->relations());

        $this->assertSame(UserPermissions::impersonate, self::fixtures()->get('USER_IMPERSONATE')->permission);
        $this->assertSame('User', self::fixtures()->get('USER_IMPERSONATE')->domain);
    }

    public function testUnknownPermissionIsRefused(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        self::fixtures()->get('NEWS_DELETE');
    }

    public function testMissingDirectoryIsSkipped(): void
    {
        $this->assertSame([], self::registryFor('App\\Security\\Permissions\\', __DIR__.'/does-not-exist')->all());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        yield 'a case without grant' => ['MissingGrant', 'ReportPermissions::edit has no #[Grant]'];
        yield 'a value starting with ROLE_' => ['RoleValue', 'must not start with ROLE_'];
        yield 'an unknown relation' => ['UnknownRelation', 'no PermissionRelation is registered as "owner"'];
        yield 'a value declared twice' => ['Duplicate', 'The permission "REPORT_VIEW" is declared by both'];
        yield 'an int backed enum' => ['IntBacked', 'must be backed by strings'];
        yield 'a grant without role nor relation' => ['EmptyGrant', 'needs at least one role or relation'];
    }

    #[DataProvider('invalidDeclarations')]
    public function testInvalidDeclarationFailsTheBuild(string $fixture, string $message): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage($message);

        self::registryFor('Enabel\\Permission\\Tests\\Fixtures\\Invalid\\'.$fixture.'\\', \dirname(__DIR__).'/Fixtures/Invalid/'.$fixture);
    }
}
