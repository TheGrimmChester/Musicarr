<?php

declare(strict_types=1);

namespace App\Plugin;

class PluginManager
{
    /** @var array<string, array> */
    private array $plugins = [];

    private string $pluginsDir;

    public function __construct(?string $pluginsDir = null)
    {
        $this->pluginsDir = $pluginsDir ?? __DIR__ . '/../../plugins';
        $this->discoverPlugins();
    }

    /**
     * Discover plugins by scanning the plugins directory.
     */
    private function discoverPlugins(): void
    {
        if (!is_dir($this->pluginsDir)) {
            return;
        }

        $pluginDirs = scandir($this->pluginsDir);
        if (false === $pluginDirs) {
            return;
        }

        foreach ($pluginDirs as $pluginDir) {
            if ('.' === $pluginDir || '..' === $pluginDir) {
                continue;
            }

            $pluginPath = $this->pluginsDir . '/' . $pluginDir;
            if (!is_dir($pluginPath)) {
                continue;
            }

            $pluginJsonPath = $pluginPath . '/plugin.json';
            if (!file_exists($pluginJsonPath)) {
                continue;
            }

            $pluginData = json_decode(file_get_contents($pluginJsonPath), true);
            if ($pluginData && isset($pluginData['name'])) {
                $this->plugins[$pluginData['name']] = $pluginData;
            }
        }
    }

    /**
     * Refresh plugin discovery (useful after installing new plugins).
     */
    public function refreshPlugins(): void
    {
        $this->plugins = [];
        $this->discoverPlugins();
    }

    /**
     * Return all plugins found in the plugins directory.
     *
     * @return array<string, array>
     */
    public function getPlugins(): array
    {
        return $this->plugins;
    }

    /**
     * Get a specific plugin by name.
     */
    public function getPlugin(string $name): ?array
    {
        return $this->plugins[$name] ?? null;
    }

    /**
     * Check if a plugin exists.
     */
    public function hasPlugin(string $name): bool
    {
        return isset($this->plugins[$name]);
    }

    /**
     * Get plugin path.
     */
    public function getPluginPath(string $name): ?string
    {
        $plugin = $this->getPlugin($name);
        if (!$plugin) {
            return null;
        }

        return $this->pluginsDir . '/' . $name;
    }

    /**
     * Get plugin bundle class.
     */
    public function getPluginBundleClass(string $name): ?string
    {
        $plugin = $this->getPlugin($name);

        return $plugin['bundle_class'] ?? null;
    }

    /**
     * Get plugin directory by bundle class.
     */
    public function getPluginDirectoryByBundleClass(string $bundleClass): ?string
    {
        foreach ($this->plugins as $pluginName => $pluginData) {
            if (isset($pluginData['bundle_class']) && $pluginData['bundle_class'] === $bundleClass) {
                return $this->getPluginPath($pluginName);
            }
        }

        return null;
    }
}
