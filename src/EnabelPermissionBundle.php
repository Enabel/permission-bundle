<?php

/*
 * This file is part of the Enabel Permission package.
 * Copyright (c) Enabel <https://enabel.be/>
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Enabel\Permission;

use Enabel\Permission\Attribute\AsPermissionRelation;
use Enabel\Permission\Command\DebugPermissionCommand;
use Enabel\Permission\DependencyInjection\PermissionDiscoveryPass;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class EnabelPermissionBundle extends AbstractBundle
{
    public function configure(DefinitionConfigurator $definition): void
    {
        $definition->rootNode()
            ->children()
                ->arrayNode('mappings')
                    ->info('Namespace prefix => directory of the permission enums, read recursively.')
                    ->useAttributeAsKey('namespace')
                    ->scalarPrototype()->end()
                    ->defaultValue(['App\\Security\\Permissions\\' => '%kernel.project_dir%/src/Security/Permissions'])
                ->end()
            ->end();
    }

    /**
     * @param array{mappings: array<string, string>} $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $container->parameters()->set(PermissionDiscoveryPass::MAPPINGS_PARAMETER, $config['mappings']);
        $container->import('../config/services.php');

        if (!class_exists(Command::class)) {
            $builder->removeDefinition(DebugPermissionCommand::class);
        }

        $builder->registerAttributeForAutoconfiguration(
            AsPermissionRelation::class,
            static function (ChildDefinition $definition, AsPermissionRelation $attribute): void {
                $definition->addTag(PermissionDiscoveryPass::RELATION_TAG, ['name' => $attribute->name]);
            },
        );
        $builder->registerForAutoconfiguration(PermissionRule::class)->addTag('enabel_permission.rule');
    }

    public function build(ContainerBuilder $container): void
    {
        $container->addCompilerPass(new PermissionDiscoveryPass());
    }
}
