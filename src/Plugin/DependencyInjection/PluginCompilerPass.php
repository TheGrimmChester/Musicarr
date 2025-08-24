<?php

declare(strict_types=1);

namespace App\Plugin\DependencyInjection;

use App\Plugin\PluginInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

class PluginCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        // Find all plugin bundles that implement PluginInterface
        $this->registerPluginBundles($container);

        // Load services and routes for all available plugins
        $this->loadPluginServices($container);

        // Ensure plugin controllers are properly tagged
        $this->ensureControllerTags($container);
    }

    private function registerPluginBundles(ContainerBuilder $container): void
    {
        // Look for services that are bundles and implement PluginInterface
        foreach ($container->getDefinitions() as $_id => $definition) {
            if (!$definition->isAbstract() && $definition->getClass()) {
                $class = $definition->getClass();

                // Check if this is a plugin bundle
                if (class_exists($class)
                    && is_subclass_of($class, Bundle::class)
                    && is_subclass_of($class, PluginInterface::class)) {
                    // Tag it as a plugin
                    $definition->addTag('app.plugin');

                    // Set the bundle as public so it can be accessed
                    $definition->setPublic(true);
                }
            }
        }
    }

    private function loadPluginServices(ContainerBuilder $container): void
    {
        // Load services for all available plugins
        foreach ($container->getDefinitions() as $_id => $definition) {
            if (!$definition->isAbstract() && $definition->getClass()) {
                $class = $definition->getClass();

                // Check if this is a plugin bundle
                if (class_exists($class)
                    && is_subclass_of($class, Bundle::class)
                    && is_subclass_of($class, PluginInterface::class)) {
                    // Plugin is available, ensure its services are loaded
                    $this->ensurePluginServices($container, $class::getPluginName());
                }
            }
        }
    }

    private function ensurePluginServices(ContainerBuilder $container, string $pluginName): void
    {
        // Ensure services that belong to this plugin are properly configured
        foreach ($container->getDefinitions() as $id => $definition) {
            if (0 === mb_strpos($id, 'Musicarr\\') && false !== mb_strpos($id, $pluginName)) {
                // Make sure the service is public and autowired
                $definition->setPublic(true);
                $definition->setAutowired(true);
                $definition->setAutoconfigured(true);
            }
        }
    }

    private function ensureControllerTags(ContainerBuilder $container): void
    {
        // Find all plugin controllers and ensure they have the right tags
        foreach ($container->getDefinitions() as $_id => $definition) {
            if (!$definition->isAbstract() && $definition->getClass()) {
                $class = $definition->getClass();

                // Check if this is a plugin controller
                if (class_exists($class)
                    && 0 === mb_strpos($class, 'Musicarr\\')
                    && false !== mb_strpos($class, 'Controller\\')) {
                    // Ensure controller tags
                    if (!$definition->hasTag('controller.service_arguments')) {
                        $definition->addTag('controller.service_arguments');
                    }

                    if (!$definition->hasTag('container.service_subscriber')) {
                        $definition->addTag('container.service_subscriber');
                    }

                    // Make sure it's public and autowired
                    $definition->setPublic(true);
                    $definition->setAutowired(true);
                    $definition->setAutoconfigured(true);
                }
            }
        }
    }
}
