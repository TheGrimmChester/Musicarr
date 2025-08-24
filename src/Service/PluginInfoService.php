<?php

declare(strict_types=1);

namespace App\Service;

use Exception;

class PluginInfoService
{
    public function __construct(
        private PluginStatusManager $pluginStatusManager,
        private ?RemotePluginDiscoveryService $remotePluginDiscoveryService = null
    ) {
    }

    /**
     * Get plugin information formatted for display in templates.
     *
     * @param array<string, array> $plugins
     *
     * @return array<string, array{name: string, version: string, author: string, description: string, installed: bool, enabled: bool, isRemote: bool, repository_url?: string, homepage_url?: string, license?: string, tags?: array<string>}>
     */
    public function getPluginInfoForDisplay(array $plugins): array
    {
        $pluginInfo = [];

        // Process local plugins first (preserve all existing functionality)
        foreach ($plugins as $pluginName => $pluginData) {
            $bundleClass = $pluginData['bundle_class'] ?? null;
            $pluginInfo[$pluginName] = [
                'name' => $pluginData['name'] ?? $pluginName,
                'version' => $pluginData['version'] ?? 'Unknown',
                'author' => $pluginData['author'] ?? 'Unknown',
                'description' => $pluginData['description'] ?? 'No description available',
                'installed' => $bundleClass ? $this->pluginStatusManager->isPluginEnabled($bundleClass) : false,
                'enabled' => $bundleClass ? $this->pluginStatusManager->isPluginEnabled($bundleClass) : false,
                'isRemote' => false,
                'repository_url' => $pluginData['repository_url'] ?? null,
                'homepage_url' => $pluginData['homepage_url'] ?? null,
                'license' => $pluginData['license'] ?? null,
                'tags' => $pluginData['tags'] ?? [],
            ];
        }

        // Get remote plugins if service is available
        if ($this->remotePluginDiscoveryService) {
            try {
                $remotePlugins = $this->remotePluginDiscoveryService->discoverRemotePlugins();

                // Add remote plugins (only those not already installed locally)
                foreach ($remotePlugins as $pluginName => $remotePlugin) {
                    if (!isset($pluginInfo[$pluginName])) {
                        $pluginInfo[$pluginName] = [
                            'name' => $remotePlugin['name'],
                            'version' => $remotePlugin['version'],
                            'author' => $remotePlugin['author'],
                            'description' => $remotePlugin['description'],
                            'installed' => false,
                            'enabled' => false,
                            'isRemote' => true,
                            'repository_url' => $remotePlugin['repository_url'],
                            'homepage_url' => $remotePlugin['homepage_url'] ?? null,
                            'license' => $remotePlugin['license'] ?? null,
                            'tags' => $remotePlugin['tags'] ?? [],
                            'last_updated' => $remotePlugin['last_updated'] ?? null,
                        ];
                    }
                }
            } catch (Exception $e) {
                // Silently fail if remote discovery is not available
                // Local plugins will still be displayed
            }
        }

        return $pluginInfo;
    }

    /**
     * Get remote plugins only.
     */
    public function getRemotePlugins(): array
    {
        if (!$this->remotePluginDiscoveryService) {
            return [];
        }

        try {
            return $this->remotePluginDiscoveryService->discoverRemotePlugins();
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Get local plugins only.
     */
    public function getLocalPlugins(array $plugins): array
    {
        $localPlugins = [];

        foreach ($plugins as $pluginName => $pluginData) {
            $bundleClass = $pluginData['bundle_class'] ?? null;
            $localPlugins[$pluginName] = [
                'name' => $pluginData['name'] ?? $pluginName,
                'version' => $pluginData['version'] ?? 'Unknown',
                'author' => $pluginData['author'] ?? 'Unknown',
                'description' => $pluginData['description'] ?? 'No description available',
                'installed' => $bundleClass ? $this->pluginStatusManager->isPluginEnabled($bundleClass) : false,
                'enabled' => $bundleClass ? $this->pluginStatusManager->isPluginEnabled($bundleClass) : false,
                'isRemote' => false,
                'repository_url' => $pluginData['repository_url'] ?? null,
                'homepage_url' => $pluginData['homepage_url'] ?? null,
                'license' => $pluginData['license'] ?? null,
                'tags' => $pluginData['tags'] ?? [],
            ];
        }

        return $localPlugins;
    }

    /**
     * Search plugins (both local and remote).
     */
    public function searchPlugins(string $query, ?array $tags = null): array
    {
        $allPlugins = $this->getPluginInfoForDisplay([]);
        $results = [];

        foreach ($allPlugins as $name => $plugin) {
            $matches = false;

            // Search in name, description, and author
            if (false !== mb_stripos($plugin['name'], $query)
                || false !== mb_stripos($plugin['description'], $query)
                || false !== mb_stripos($plugin['author'], $query)) {
                $matches = true;
            }

            // Filter by tags if specified
            if ($tags && !empty($tags)) {
                $pluginTags = $plugin['tags'] ?? [];
                $tagMatch = false;
                foreach ($tags as $tag) {
                    if (\in_array($tag, $pluginTags, true)) {
                        $tagMatch = true;

                        break;
                    }
                }
                $matches = $matches && $tagMatch;
            }

            if ($matches) {
                $results[$name] = $plugin;
            }
        }

        return $results;
    }

    /**
     * Get plugin categories based on tags.
     */
    public function getPluginCategories(): array
    {
        $allPlugins = $this->getPluginInfoForDisplay([]);
        $categories = [];

        foreach ($allPlugins as $plugin) {
            $tags = $plugin['tags'] ?? [];
            foreach ($tags as $tag) {
                if (!isset($categories[$tag])) {
                    $categories[$tag] = 0;
                }
                ++$categories[$tag];
            }
        }

        arsort($categories);

        return $categories;
    }
}
