<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Plugin\PluginManager;
use App\Service\PluginStatusManager;
use App\Service\RemotePluginInstallerService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

#[AutoconfigureTag('app.task_processor')]
class RemotePluginInstallTaskProcessor implements TaskProcessorInterface
{
    private string $projectRoot;

    public function __construct(
        private RemotePluginInstallerService $remotePluginInstaller,
        private PluginManager $pluginManager,
        private PluginStatusManager $pluginStatusManager,
        private LoggerInterface $logger
    ) {
        $this->projectRoot = __DIR__ . '/../../../';
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $data = $task->getMetadata() ?? [];
            $repositoryUrl = $data['repository_url'] ?? null;
            $pluginName = $data['plugin_name'] ?? null;
            $branch = $data['branch'] ?? 'main';

            if (!$repositoryUrl || !$pluginName) {
                return TaskProcessorResult::failure('Repository URL and plugin name are required');
            }

            $this->logger->info('Starting remote plugin installation', [
                'pluginName' => $pluginName,
                'repositoryUrl' => $repositoryUrl,
                'branch' => $branch,
            ]);

            // Step 1: Clone repository and install dependencies
            $result = $this->remotePluginInstaller->installPlugin(
                $repositoryUrl,
                $pluginName,
                $branch
            );

            if (!$result['success']) {
                return TaskProcessorResult::failure('Failed to clone repository: ' . $result['error']);
            }

            $this->logger->info('Repository cloned successfully, now installing plugin', [
                'pluginName' => $pluginName,
                'pluginPath' => $result['pluginPath'],
            ]);

            // Step 2: Refresh plugin discovery to see the newly cloned plugin
            $this->pluginManager->refreshPlugins();

            // Step 3: Get plugin data and install it like a local plugin
            $pluginData = $this->pluginManager->getPlugin($pluginName);
            if (!$pluginData) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' not found after cloning");
            }

            $bundleClass = $pluginData['bundle_class'] ?? null;
            if (!$bundleClass) {
                return TaskProcessorResult::failure("Plugin '{$pluginName}' has no bundle class defined");
            }

            // Step 4: Check if plugin is already installed
            if ($this->pluginStatusManager->isPluginEnabled($bundleClass)) {
                return TaskProcessorResult::success(
                    "Plugin '{$pluginName}' is already installed",
                    [
                        'pluginName' => $pluginName,
                        'status' => 'already_installed',
                        'bundleClass' => $bundleClass,
                    ]
                );
            }

            // Step 5: Install the plugin using the status manager (same as local installation)
            if (!$this->pluginStatusManager->installPlugin($bundleClass)) {
                return TaskProcessorResult::failure("Failed to install plugin '{$pluginName}'");
            }

            // Step 6: Update database schema after plugin installation
            $this->updateDatabaseSchema();

            $this->logger->info('Successfully installed remote plugin', [
                'pluginName' => $pluginName,
                'bundleClass' => $bundleClass,
                'repositoryUrl' => $repositoryUrl,
            ]);

            return TaskProcessorResult::success(
                "Successfully installed remote plugin '{$pluginName}' from {$repositoryUrl}",
                [
                    'pluginName' => $pluginName,
                    'status' => 'installed',
                    'bundleClass' => $bundleClass,
                    'repositoryUrl' => $repositoryUrl,
                    'branch' => $branch,
                    'version' => $pluginData['version'] ?? 'Unknown',
                    'author' => $pluginData['author'] ?? 'Unknown',
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to install remote plugin', [
                'pluginName' => $data['plugin_name'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return TaskProcessorResult::failure('Failed to install remote plugin: ' . $e->getMessage());
        }
    }

    /**
     * Update the database schema to include any new entities or changes from the plugin.
     */
    private function updateDatabaseSchema(): void
    {
        try {
            $this->logger->info('Updating database schema after remote plugin installation');

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
        return [Task::TYPE_REMOTE_PLUGIN_INSTALL];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_REMOTE_PLUGIN_INSTALL === $task->getType();
    }
}
