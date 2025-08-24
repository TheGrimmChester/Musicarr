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
class PluginInstallTaskProcessor implements TaskProcessorInterface
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

            if (!$pluginName) {
                return TaskProcessorResult::failure('No plugin name provided');
            }

            $this->logger->info("Installing plugin: {$pluginName}");

            // Get the plugin data
            $pluginData = $this->pluginManager->getPlugin($pluginName);
            if (!$pluginData) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' not found");
            }

            $bundleClass = $pluginData['bundle_class'] ?? null;
            if (!$bundleClass) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' has no bundle class defined");
            }

            // Check if plugin is already installed
            if ($this->pluginStatusManager->isPluginEnabled($bundleClass)) {
                return TaskProcessorResult::success(
                    "Plugin '{$pluginName}' is already installed",
                    ['pluginName' => $pluginName, 'status' => 'already_installed']
                );
            }

            // Install the plugin using the status manager
            if (!$this->pluginStatusManager->installPlugin($bundleClass)) {
                return TaskProcessorResult::failure("Failed to install plugin '{$pluginName}'");
            }

            // Update database schema after plugin installation
            $this->updateDatabaseSchema();

            // Note: We can't call install() method since we only have JSON data
            // The actual installation is handled by the status manager

            $this->logger->info("Successfully installed plugin: {$pluginName}");

            return TaskProcessorResult::success(
                "Successfully installed plugin '{$pluginName}'",
                [
                    'pluginName' => $pluginName,
                    'status' => 'installed',
                    'version' => $pluginData['version'] ?? 'Unknown',
                    'author' => $pluginData['author'] ?? 'Unknown',
                    'bundleClass' => $bundleClass,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to install plugin', [
                'pluginName' => $metadata['plugin_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure('Failed to install plugin: ' . $e->getMessage());
        }
    }

    /**
     * Update the database schema to include any new entities or changes from the plugin.
     */
    private function updateDatabaseSchema(): void
    {
        try {
            $this->logger->info('Updating database schema after plugin installation');

            $process = new Process(['php', 'bin/console', 'doctrine:migrations:migrate', '-n'], $this->projectRoot);
            $process->setTimeout(300); // 5 minutes timeout
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

            $this->logger->info('Database schema updated successfully');
        } catch (Exception $e) {
            $this->logger->warning('Failed to update database schema: ' . $e->getMessage());
            // Don't throw the exception - plugin installation succeeded, schema update is just a cleanup step
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PLUGIN_INSTALL];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PLUGIN_INSTALL === $task->getType();
    }
}
