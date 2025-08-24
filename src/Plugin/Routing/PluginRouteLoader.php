<?php

declare(strict_types=1);

namespace App\Plugin\Routing;

use App\Plugin\PluginManager;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\Config\Loader\LoaderResolverInterface;
use Symfony\Component\Routing\Loader\YamlFileLoader;
use Symfony\Component\Routing\RouteCollection;
use Throwable;

class PluginRouteLoader implements LoaderInterface
{
    private PluginManager $pluginManager;
    private string $projectDir;
    private ?LoaderResolverInterface $resolver = null;

    public function __construct(PluginManager $pluginManager, string $projectDir)
    {
        $this->pluginManager = $pluginManager;
        $this->projectDir = $projectDir;
    }

    public function load($_resource, $_type = null): RouteCollection
    {
        $collection = new RouteCollection();

        try {
            // Load routes from all available plugins
            $plugins = $this->pluginManager->getPlugins();
            foreach ($plugins as $pluginName => $plugin) {
                $pluginRoutesFile = $this->projectDir . '/plugins/' . $pluginName . '/config/routes.yaml';

                if (is_file($pluginRoutesFile)) {
                    $loader = new YamlFileLoader(new FileLocator(\dirname($pluginRoutesFile)));
                    $loader->setResolver($this->resolver);
                    $pluginRoutes = $loader->load('routes.yaml');

                    // Add all routes from this plugin
                    $collection->addCollection($pluginRoutes);
                }
            }
        } catch (Throwable $e) {
            // If we can't load plugins, don't load any plugin routes
            // This ensures the system works even if there are issues
        }

        return $collection;
    }

    public function supports($_resource, $_type = null): bool
    {
        return 'plugin' === $_type;
    }

    public function getResolver(): LoaderResolverInterface
    {
        return $this->resolver;
    }

    public function setResolver(LoaderResolverInterface $resolver): void
    {
        $this->resolver = $resolver;
    }
}
