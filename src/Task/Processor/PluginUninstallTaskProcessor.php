<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Plugin\PluginManager;
use App\Service\PluginStatusManager;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class PluginUninstallTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private PluginManager $pluginManager,
        private PluginStatusManager $pluginStatusManager,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $metadata = $task->getMetadata() ?? [];
            $pluginName = $metadata['plugin_name'] ?? null;

            if (!$pluginName) {
                return TaskProcessorResult::failure('No plugin name provided');
            }

            $this->logger->info("Uninstalling plugin: {$pluginName}");

            // Get the plugin data
            $pluginData = $this->pluginManager->getPlugin($pluginName);
            if (!$pluginData) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' not found");
            }

            $bundleClass = $pluginData['bundle_class'] ?? null;
            if (!$bundleClass) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' has no bundle class defined");
            }

            // Check if plugin is already uninstalled
            if (!$this->pluginStatusManager->isPluginEnabled($bundleClass)) {
                return TaskProcessorResult::success(
                    "Plugin '{$pluginName}' is already uninstalled",
                    ['pluginName' => $pluginName, 'status' => 'already_uninstalled']
                );
            }

            // Uninstall the plugin using the status manager
            if (!$this->pluginStatusManager->uninstallPlugin($bundleClass)) {
                return TaskProcessorResult::failure("Failed to uninstall plugin '{$pluginName}'");
            }

            $this->logger->info("Successfully uninstalled plugin: {$pluginName}");

            return TaskProcessorResult::success(
                "Successfully uninstalled plugin '{$pluginName}'",
                [
                    'pluginName' => $pluginName,
                    'status' => 'uninstalled',
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to uninstall plugin', [
                'pluginName' => $metadata['plugin_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure('Failed to uninstall plugin: ' . $e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PLUGIN_UNINSTALL];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PLUGIN_UNINSTALL === $task->getType();
    }
}
