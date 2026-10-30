<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Enabel\Permission\Command\DebugPermissionCommand;
use Enabel\Permission\DependencyInjection\PermissionDiscoveryPass;
use Enabel\Permission\Matrix\PermissionMatrix;
use Enabel\Permission\PermissionRegistry;
use Enabel\Permission\Voter\PermissionVoter;

return static function (ContainerConfigurator $container): void {
    $services = $container->services();

    // The compiled permissions are set by PermissionDiscoveryPass.
    $services->set(PermissionRegistry::class)
        ->args(['$compiled' => []]);

    $services->set(PermissionVoter::class)
        ->args([
            service(PermissionRegistry::class),
            service('security.role_hierarchy'),
            tagged_locator(PermissionDiscoveryPass::RELATION_TAG, 'name'),
            tagged_iterator('enabel_permission.rule'),
        ])
        ->tag('security.voter');

    $services->set(PermissionMatrix::class)
        ->args([
            service(PermissionRegistry::class),
            service('security.role_hierarchy'),
            param('security.role_hierarchy.roles'),
        ]);

    $services->set(DebugPermissionCommand::class)
        ->args([service(PermissionRegistry::class)])
        ->tag('console.command');
};
