<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\Service\RemotePluginInstallerService;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class PluginReferenceChangeTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private RemotePluginInstallerService $remotePluginInstaller,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $data = $task->getMetadata() ?? [];
            $pluginName = $data['plugin_name'] ?? null;
            $reference = $data['reference'] ?? null;
            $referenceType = $data['reference_type'] ?? 'branch';

            if (!$pluginName || !$reference) {
                return TaskProcessorResult::failure('Missing required data: plugin_name and reference');
            }

            $this->logger->info('Changing plugin reference', [
                'pluginName' => $pluginName,
                'reference' => $reference,
                'referenceType' => $referenceType,
            ]);

            // Change the plugin reference
            $result = $this->remotePluginInstaller->changePluginReference(
                $pluginName,
                $reference,
                $referenceType
            );

            if (!$result['success']) {
                return TaskProcessorResult::failure($result['error']);
            }

            $this->logger->info('Successfully changed plugin reference', [
                'pluginName' => $pluginName,
                'reference' => $reference,
                'referenceType' => $referenceType,
            ]);

            return TaskProcessorResult::success(
                $result['message'],
                [
                    'pluginName' => $pluginName,
                    'reference' => $reference,
                    'referenceType' => $referenceType,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to change plugin reference', [
                'pluginName' => $pluginName ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return TaskProcessorResult::failure(
                'Failed to change plugin reference: ' . $e->getMessage()
            );
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_PLUGIN_REFERENCE_CHANGE];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_PLUGIN_REFERENCE_CHANGE === $task->getType();
    }
}
