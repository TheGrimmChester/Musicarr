<?php

declare(strict_types=1);

namespace App\Service;

use App\Plugin\PluginManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class PluginStatusManager
{
    private string $bundlesFile;
    private string $projectRoot;

    public function __construct(
        private LoggerInterface $logger,
        private Filesystem $filesystem,
        private PluginManager $pluginManager
    ) {
        $this->bundlesFile = __DIR__ . '/../../config/bundles_enabled.json';
        $this->projectRoot = __DIR__ . '/../../';
    }

    /**
     * Get all enabled plugins from bundles_enabled.json.
     */
    public function getEnabledPlugins(): array
    {
        if (!file_exists($this->bundlesFile)) {
            return [];
        }

        $enabledBundles = json_decode(file_get_contents($this->bundlesFile), true) ?? [];

        return array_filter($enabledBundles, fn ($enabled) => true === $enabled);
    }

    /**
     * Check if a plugin is enabled.
     */
    public function isPluginEnabled(string $bundleClass): bool
    {
        $enabledBundles = $this->getEnabledPlugins();

        return isset($enabledBundles[$bundleClass]) && $enabledBundles[$bundleClass];
    }

    /**
     * Enable a plugin.
     */
    public function enablePlugin(string $bundleClass): bool
    {
        try {
            $enabledBundles = $this->getEnabledBundles();
            $enabledBundles[$bundleClass] = true;

            $this->saveEnabledBundles($enabledBundles);

            $this->logger->info("Plugin enabled: {$bundleClass}");

            // Rebuild assets and clear cache after enabling plugin
            $this->rebuildAfterPluginChange($bundleClass, 'enabled');

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to enable plugin: {$bundleClass}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Disable a plugin.
     */
    public function disablePlugin(string $bundleClass): bool
    {
        try {
            $enabledBundles = $this->getEnabledBundles();
            $enabledBundles[$bundleClass] = false;

            $this->saveEnabledBundles($enabledBundles);

            $this->logger->info("Plugin disabled: {$bundleClass}");

            // Rebuild assets and clear cache after disabling plugin
            $this->rebuildAfterPluginChange($bundleClass, 'disabled');

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to disable plugin: {$bundleClass}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Install a plugin (add to bundles_enabled.json).
     */
    public function installPlugin(string $bundleClass): bool
    {
        try {
            $enabledBundles = $this->getEnabledBundles();
            $enabledBundles[$bundleClass] = true;

            $this->saveEnabledBundles($enabledBundles);

            $this->logger->info("Plugin installed: {$bundleClass}");

            // Rebuild assets and clear cache after installing plugin
            $this->rebuildAfterPluginChange($bundleClass, 'installed');

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to install plugin: {$bundleClass}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Uninstall a plugin (remove from bundles_enabled.json and delete plugin directory).
     */
    public function uninstallPlugin(string $bundleClass): bool
    {
        try {
            // First, remove from bundles configuration
            $enabledBundles = $this->getEnabledBundles();
            unset($enabledBundles[$bundleClass]);

            $this->saveEnabledBundles($enabledBundles);

            $this->logger->info("Plugin uninstalled from bundles: {$bundleClass}");

            // Now remove the plugin directory from filesystem
            $this->removePluginDirectory($bundleClass);

            // Rebuild assets and clear cache after uninstalling plugin
            $this->rebuildAfterPluginChange($bundleClass, 'uninstalled');

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to uninstall plugin: {$bundleClass}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Upgrade a plugin (clear cache and rebuild assets).
     */
    public function upgradePlugin(string $bundleClass): bool
    {
        try {
            $this->logger->info("Plugin upgrade initiated: {$bundleClass}");

            // Rebuild assets and clear cache for plugin upgrade
            $this->rebuildAfterPluginChange($bundleClass, 'upgraded');

            return true;
        } catch (Exception $e) {
            $this->logger->error("Failed to upgrade plugin: {$bundleClass}", ['error' => $e->getMessage()]);

            return false;
        }
    }

    /**
     * Rebuild assets and clear cache after plugin changes.
     */
    private function rebuildAfterPluginChange(string $bundleClass, string $operation): void
    {
        try {
            $this->logger->info("Rebuilding assets and clearing cache after plugin {$operation}: {$bundleClass}");

            // Clear Symfony cache
            $this->clearCache();

            // Rebuild assets
            $this->rebuildAssets();

            $this->logger->info("Successfully rebuilt assets and cleared cache for plugin: {$bundleClass}");
        } catch (Exception $e) {
            $this->logger->error("Failed to rebuild assets/clear cache for plugin: {$bundleClass}", [
                'operation' => $operation,
                'error' => $e->getMessage(),
            ]);
            // Don't throw the exception - plugin operation succeeded, this is just cleanup
        }
    }

    /**
     * Clear Symfony cache.
     */
    private function clearCache(): void
    {
        try {
            // Clear dev cache
            $process = new Process(['php', 'bin/console', 'cache:clear'], $this->projectRoot);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->logger->info('Cache cleared successfully');
        } catch (Exception $e) {
            $this->logger->warning('Failed to clear cache: ' . $e->getMessage());
        }
    }

    /**
     * Rebuild assets.
     */
    private function rebuildAssets(): void
    {
        try {
            // Check if npm is available
            $process = new Process(['which', 'npm'], $this->projectRoot);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger->warning('npm not found, skipping asset rebuild');

                return;
            }

            // Rebuild assets
            $process = new Process(['npm', 'run', 'build'], $this->projectRoot);
            $process->setTimeout(600); // 10 minutes timeout for asset building
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->logger->info('Assets rebuilt successfully');
        } catch (Exception $e) {
            $this->logger->warning('Failed to rebuild assets: ' . $e->getMessage());
        }
    }

    /**
     * Get all bundles (enabled and disabled).
     */
    private function getEnabledBundles(): array
    {
        if (!file_exists($this->bundlesFile)) {
            return [];
        }

        return json_decode(file_get_contents($this->bundlesFile), true) ?? [];
    }

    /**
     * Save enabled bundles to file.
     */
    private function saveEnabledBundles(array $enabledBundles): void
    {
        // Ensure directory exists
        $dir = \dirname($this->bundlesFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        // Save with pretty formatting
        file_put_contents($this->bundlesFile, json_encode($enabledBundles, \JSON_PRETTY_PRINT));
    }

    /**
     * Remove a plugin directory from the filesystem.
     */
    private function removePluginDirectory(string $bundleClass): void
    {
        $pluginDir = $this->pluginManager->getPluginDirectoryByBundleClass($bundleClass);
        if ($pluginDir && $this->filesystem->exists($pluginDir)) {
            $this->logger->info("Removing plugin directory: {$pluginDir}");

            // Check if it's a symbolic link
            if (is_link($pluginDir)) {
                $this->logger->info('Plugin directory is a symbolic link, removing link');
                $this->filesystem->remove($pluginDir);
                $this->logger->info("Symbolic link removed: {$pluginDir}");
            } else {
                // It's a regular directory, remove it completely
                $this->filesystem->remove($pluginDir);
                $this->logger->info("Plugin directory removed: {$pluginDir}");
            }
        } else {
            $this->logger->warning("Plugin directory not found: {$pluginDir}");
        }
    }
}
