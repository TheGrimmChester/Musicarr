<?php

declare(strict_types=1);

namespace App\Service;

use Exception;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class RemotePluginDiscoveryService
{
    private const PLUGIN_REPO_OWNER = 'TheGrimmChester';
    private const PLUGIN_REPO_NAME = 'Musicarr-Plugins';
    private const CACHE_KEY = 'remote_plugins_discovery';
    private const CACHE_TTL = 3600; // 1 hour

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheItemPoolInterface $cache
    ) {
    }

    /**
     * Discover remote plugins from the central repository.
     */
    public function discoverRemotePlugins(): array
    {
        // Check cache first
        $cacheItem = $this->cache->getItem(self::CACHE_KEY);
        if ($cacheItem->isHit()) {
            return $cacheItem->get();
        }

        try {
            $plugins = $this->fetchPluginsFromRepository();

            // Cache the result
            $cacheItem->set($plugins);
            $cacheItem->expiresAfter(self::CACHE_TTL);
            $this->cache->save($cacheItem);

            return $plugins;
        } catch (Exception $e) {
            // Return empty array if discovery fails
            return [];
        }
    }

    /**
     * Fetch plugins from the central repository using raw GitHub URLs.
     */
    private function fetchPluginsFromRepository(): array
    {
        // Use raw GitHub URL instead of API to avoid rate limiting
        $pluginsJsonUrl = 'https://raw.githubusercontent.com/' . self::PLUGIN_REPO_OWNER . '/' . self::PLUGIN_REPO_NAME . '/main/plugins.json';

        try {
            $response = $this->httpClient->request('GET', $pluginsJsonUrl, [
                'headers' => [
                    'User-Agent' => 'Musicarr-Plugin-Discovery/1.0',
                ],
            ]);

            if (200 !== $response->getStatusCode()) {
                throw new Exception("Failed to fetch plugins.json: HTTP {$response->getStatusCode()}");
            }

            $content = $response->getContent();
            $pluginsData = json_decode($content, true);

            if (!\is_array($pluginsData)) {
                throw new Exception('Invalid JSON format in plugins.json');
            }

            return $this->normalizePluginData($pluginsData);
        } catch (Exception $e) {
            throw new Exception('Failed to fetch plugins from repository: ' . $e->getMessage());
        }
    }

    /**
     * Normalize plugin data to ensure consistent structure.
     */
    private function normalizePluginData(array $pluginsData): array
    {
        $normalized = [];

        foreach ($pluginsData as $plugin) {
            if ($this->isValidPluginData($plugin)) {
                $pluginName = $plugin['name'];
                $normalized[$pluginName] = [
                    'name' => $plugin['name'],
                    'version' => $plugin['version'] ?? '1.0.0',
                    'author' => $plugin['author'] ?? 'Unknown',
                    'description' => $plugin['description'] ?? '',
                    'repository_url' => $plugin['repository_url'] ?? '',
                    'homepage_url' => $plugin['homepage_url'] ?? null,
                    'license' => $plugin['license'] ?? null,
                    'tags' => $plugin['tags'] ?? [],
                ];
            }
        }

        return $normalized;
    }

    /**
     * Validate that plugin data has required fields.
     */
    private function isValidPluginData(array $plugin): bool
    {
        $requiredFields = ['name', 'repository_url'];

        foreach ($requiredFields as $field) {
            if (!isset($plugin[$field]) || empty($plugin[$field])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get a specific remote plugin by name.
     */
    public function getRemotePlugin(string $pluginName): ?array
    {
        $plugins = $this->discoverRemotePlugins();

        return $plugins[$pluginName] ?? null;
    }

    /**
     * Search remote plugins by query.
     */
    public function searchRemotePlugins(string $query): array
    {
        $plugins = $this->discoverRemotePlugins();
        $query = mb_strtolower($query);

        return array_filter($plugins, function ($plugin) use ($query) {
            return str_contains(mb_strtolower($plugin['name']), $query)
                   || str_contains(mb_strtolower($plugin['description']), $query)
                   || str_contains(mb_strtolower($plugin['author']), $query);
        });
    }

    /**
     * Clear the cache.
     */
    public function clearCache(): void
    {
        $this->cache->deleteItem($this->CACHE_KEY);
    }

    /**
     * Refresh remote plugins (clear cache and rediscover).
     */
    public function refreshRemotePlugins(): array
    {
        $this->clearCache();

        return $this->discoverRemotePlugins();
    }
}
