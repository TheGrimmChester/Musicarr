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
class PluginEnableTaskProcessor implements TaskProcessorInterface
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

            $this->logger->info("Enabling plugin: {$pluginName}");

            // Get the plugin data
            $pluginData = $this->pluginManager->getPlugin($pluginName);
            if (!$pluginData) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' not found");
            }

            $bundleClass = $pluginData['bundle_class'] ?? null;
            if (!$bundleClass) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' has no bundle class defined");
            }

            // Check if plugin is already enabled
            if ($this->pluginStatusManager->isPluginEnabled($bundleClass)) {
                return TaskProcessorResult::success(
                    "Plugin '{$pluginName}' is already enabled",
                    ['pluginName' => $pluginName, 'status' => 'already_enabled']
                );
            }

            // Enable the plugin using the status manager
            if (!$this->pluginStatusManager->enablePlugin($bundleClass)) {
                return TaskProcessorResult::failure("Failed to enable plugin '{$pluginName}'");
            }

            // Note: We can't call enable() method since we only have JSON data
            // The actual enabling is handled by the status manager

            $this->logger->info("Successfully enabled plugin: {$pluginName}");

            return TaskProcessorResult::success(
                "Successfully enabled plugin '{$pluginName}'",
                [
                    'pluginName' => $pluginName,
                    'status' => 'enabled',
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to enable plugin', [
                'pluginName' => $metadata['plugin_name'] ?? 'unknown',
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure('Failed to enable plugin: ' . $e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PLUGIN_ENABLE];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PLUGIN_ENABLE === $task->getType();
    }
}
