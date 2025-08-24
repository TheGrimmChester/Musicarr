<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Plugin\PluginManager;
use App\Service\PluginStatusManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AutoconfigureTag('app.task_processor')]
class PluginUpgradeTaskProcessor implements TaskProcessorInterface
{
    private string $projectRoot;

    public function __construct(
        private PluginManager $pluginManager,
        private PluginStatusManager $pluginStatusManager,
        private LoggerInterface $logger
    ) {
        $this->projectRoot = __DIR__ . '/../../../';
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $metadata = $task->getMetadata() ?? [];
            $pluginName = $metadata['plugin_name'] ?? null;
            $targetVersion = $metadata['target_version'] ?? null;

            if (!$pluginName) {
                return TaskProcessorResult::failure('No plugin name provided');
            }

            $this->logger->info("Upgrading plugin: {$pluginName}" . ($targetVersion ? " to version {$targetVersion}" : ''));

            // Get the plugin data
            $pluginData = $this->pluginManager->getPlugin($pluginName);
            if (!$pluginData) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' not found");
            }

            $bundleClass = $pluginData['bundle_class'] ?? null;
            if (!$bundleClass) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' has no bundle class defined");
            }

            // Check if plugin is installed
            if (!$this->pluginStatusManager->isPluginEnabled($bundleClass)) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' is not installed");
            }

            // Get current version from plugin data
            $currentVersion = $pluginData['version'] ?? 'Unknown';

            // Call the upgrade method which will handle cache clearing and asset rebuilding
            if (!$this->pluginStatusManager->upgradePlugin($bundleClass)) {
                return TaskProcessorResult::failure("Failed to upgrade plugin '{$pluginName}'");
            }

            // Update database schema after plugin upgrade
            $this->updateDatabaseSchema();

            $this->logger->info("Successfully upgraded plugin: {$pluginName}");

            return TaskProcessorResult::success(
                "Successfully upgraded plugin '{$pluginName}'",
                [
                    'pluginName' => $pluginName,
                    'status' => 'upgraded',
                    'previousVersion' => $currentVersion,
                    'currentVersion' => $pluginData['version'] ?? 'Unknown',
                    'targetVersion' => $targetVersion,
                    'bundleClass' => $bundleClass,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to upgrade plugin', [
                'pluginName' => $metadata['plugin_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure('Failed to upgrade plugin: ' . $e->getMessage());
        }
    }

    /**
     * Update the database schema to include any new entities or changes from the plugin upgrade.
     */
    private function updateDatabaseSchema(): void
    {
        try {
            $this->logger->info('Updating database schema after plugin upgrade');

            $process = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '-n'], $this->projectRoot);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->logger->info('Database schema updated successfully');
        } catch (Exception $e) {
            $this->logger->warning('Failed to update database schema: ' . $e->getMessage());
            // Don't throw the exception - plugin upgrade succeeded, schema update is just a cleanup step
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PLUGIN_UPGRADE];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PLUGIN_UPGRADE === $task->getType();
    }
}
