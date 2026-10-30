<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission\Tests\Kernel;

use Enabel\Permission\EnabelPermissionBundle;
use Enabel\Permission\Matrix\PermissionMatrix;
use Enabel\Permission\Tests\Fixtures\Relation\CreatorRelation;
use Enabel\Permission\Tests\Fixtures\Rule\NotYourselfRule;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;

/**
 * A minimal application using the bundle as an app would: autoconfigured
 * relation and rule, the fixture enums as its permissions.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new EnabelPermissionBundle();
    }

    public function getProjectDir(): string
    {
        return \dirname(__DIR__, 2);
    }

    public function getCacheDir(): string
    {
        return sys_get_temp_dir().'/enabel-permission-bundle/'.md5(__FILE__).'/cache';
    }

    public function getLogDir(): string
    {
        return sys_get_temp_dir().'/enabel-permission-bundle/'.md5(__FILE__).'/log';
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'test' => true,
            'secret' => 'test',
            'http_method_override' => false,
            'default_locale' => 'en',
            'translator' => ['fallbacks' => ['en']],
        ]);

        $container->extension('security', [
            'role_hierarchy' => [
                'ROLE_ADMIN' => ['ROLE_NEWS_EDITOR'],
                'ROLE_NEWS_EDITOR' => ['ROLE_USER'],
                'ROLE_EMPLOYEE' => ['ROLE_USER'],
            ],
            'providers' => ['memory' => ['memory' => null]],
            'firewalls' => ['main' => ['lazy' => true]],
        ]);

        $container->extension('enabel_permission', [
            'mappings' => ['Enabel\\Permission\\Tests\\Fixtures\\Permissions\\' => '%kernel.project_dir%/tests/Fixtures/Permissions'],
        ]);

        $container->services()
            ->defaults()->autowire()->autoconfigure()
            ->set(CreatorRelation::class)
            ->set(NotYourselfRule::class)
            // Injected by the app's own controller; public here for the tests to fetch it.
            ->alias('test.permission_matrix', PermissionMatrix::class)->public();
    }
}
