<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Entity\Task;
use App\LibraryScanning\LibraryScanChain;
use App\Repository\LibraryRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class ScanLibraryTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private LibraryRepository $libraryRepository,
        private LoggerInterface $logger,
        private LibraryScanChain $libraryScanChain,
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $libraryId = $task->getEntityId();
            $metadata = $task->getMetadata() ?? [];
            $dryRun = $metadata['dry_run'] ?? false;
            $forceAnalysis = $metadata['force_analysis'] ?? false;

            if (!$libraryId) {
                return TaskProcessorResult::failure('No library ID provided');
            }

            $this->logger->info('Processing scan library task', [
                'library_id' => $libraryId,
                'dry_run' => $dryRun,
                'force_analysis' => $forceAnalysis,
            ]);

            // Check if library exists
            $library = $this->libraryRepository->find($libraryId);
            if (!$library) {
                return TaskProcessorResult::failure("Library {$libraryId} not found");
            }

            // Execute scan using the shared service
            $result = $this->libraryScanChain->executeChain($library, $metadata);

            $this->logger->info('Library scan completed successfully', [
                'library_id' => $libraryId,
                'files_dispatched' => $result['files_dispatched'],
                'removed_files' => $result['removed_files'],
            ]);

            return TaskProcessorResult::success(
                \sprintf('Successfully scanned library "%s"', $library->getName()),
                [
                    'libraryId' => $library->getId(),
                    'libraryName' => $library->getName(),
                    'filesDispatched' => $result['files_dispatched'],
                    'removedFiles' => $result['removed_files'],
                    'dryRun' => $dryRun,
                    'forceAnalysis' => $forceAnalysis,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Failed to scan library', [
                'library_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_SCAN_LIBRARY];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_SCAN_LIBRARY === $task->getType();
    }
}
