<?php

declare(strict_types=1);

namespace App\Task\Command;

use App\Entity\Task;
use App\Task\Processor\TaskProcessorInterface;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use LogicException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Command\SignalableCommandInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\Lock\LockFactory;
use Throwable;

#[AsCommand(
    name: 'app:process-tasks',
    description: 'Process pending tasks from the task system',
)]
class ProcessTasksCommand extends Command implements SignalableCommandInterface
{
    private array $processors = [];
    private ?Task $currentTask = null;
    private bool $shouldStop = false;

    public function __construct(
        private TaskFactory $taskService,
        private LoggerInterface $logger,
        #[AutowireIterator('app.task_processor')]
        iterable $processors,
        private EntityManagerInterface $entityManager,
        private readonly LockFactory $lockFactory,
        private readonly ManagerRegistry $managerRegistry,
    ) {
        parent::__construct();

        // Index processors by task type
        foreach ($processors as $processor) {
            if ($processor instanceof TaskProcessorInterface) {
                foreach ($processor->getSupportedTaskTypes() as $taskType) {
                    $this->processors[$taskType] = $processor;
                }
            }
        }
    }

    protected function configure(): void
    {
        $this
            ->addOption('limit', null, InputOption::VALUE_OPTIONAL, 'Maximum number of tasks to process', 10)
            ->addOption('type', null, InputOption::VALUE_OPTIONAL, 'Process only tasks of specific type')
            ->addOption('max-execution-time', null, InputOption::VALUE_OPTIONAL, 'Maximum execution time in seconds', 300)
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Show what would be processed without actually processing')
            ->addOption('single', null, InputOption::VALUE_NONE, 'Process only one task and exit')
            ->addOption('continuous', null, InputOption::VALUE_NONE, 'Run continuously, processing tasks as they appear')
            ->addOption('sleep-interval', null, InputOption::VALUE_OPTIONAL, 'Sleep interval in seconds when no tasks are found (continuous mode)', 5);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $this->registerShutdownFunction();

        try {
            $options = $this->parseInputOptions($input);
            $this->displayModeNotes($io, $options);
            $this->cancelStaleTasks($io);

            $startTime = time();
            $processedCount = 0;

            do {
                $hasProcessedAny = false;

                // Ensure entity manager is open before processing tasks
                $this->ensureEntityManagerIsOpen();

                $this->entityManager->clear();

                $tasks = $this->claimTasks($options['typeFilter'], $options['single'] ? 1 : $options['limit'], $options['dryRun']);

                if (empty($tasks)) {
                    if ($options['continuous']) {
                        $this->handleNoTasksInContinuousMode($io, $options, $startTime);

                        continue;
                    }
                    $io->success('No pending tasks found.');

                    break;
                }

                $io->section(\sprintf('Processing %d pending task(s)', \count($tasks)));
                $processedCount += $this->processTaskBatch($tasks, $io, $options, $startTime, $hasProcessedAny);

                if (!$options['continuous']) {
                    break;
                }

                $this->handleContinuousModeSleep($hasProcessedAny, $options);
            } while ($options['continuous']);

            $this->displayCompletionMessage($io, $processedCount, $startTime);

            return Command::SUCCESS;
        } catch (Throwable $e) {
            $io->error('Critical error during task processing: ' . $e->getMessage());
            $this->logger->error('Critical error during task processing', [
                'exception' => $e,
                'message' => $e->getMessage(),
            ]);

            // Try to ensure entity manager is open for any cleanup operations
            try {
                $this->ensureEntityManagerIsOpen();
            } catch (Throwable $emException) {
                $this->logger->error('Failed to reopen entity manager during error handling', [
                    'exception' => $emException,
                ]);
            }

            return Command::FAILURE;
        }
    }

    private function getPendingTasks(?string $typeFilter, int $limit): array
    {
        if ($typeFilter) {
            return $this->taskService->getTaskRepository()->createQueryBuilder('t')
                ->where('t.status = :status')
                ->andWhere('t.type = :type')
                ->setParameter('status', Task::STATUS_PENDING)
                ->setParameter('type', $typeFilter)
                ->orderBy('t.priority', 'DESC')
                ->addOrderBy('t.createdAt', 'ASC')
                ->setMaxResults($limit)
                ->getQuery()
                ->getResult();
        }

        return $this->taskService->getPendingTasks($limit);
    }

    /**
     * Claim tasks by setting them to running under a short-lived queue lock to avoid collisions.
     */
    private function claimTasks(?string $typeFilter, int $limit, bool $dryRun): array
    {
        if ($dryRun) {
            return $this->getPendingTasks($typeFilter, $limit);
        }

        $lock = $this->lockFactory->createLock('task-queue-claim', 30);
        $lock->acquire(true);

        try {
            $tasks = $this->getPendingTasks($typeFilter, $limit);

            foreach ($tasks as $task) {
                if (Task::STATUS_PENDING === $task->getStatus()) {
                    // Mark as running immediately to reserve this task
                    $this->taskService->startTask($task);
                }
            }

            return $tasks;
        } finally {
            $lock->release();
        }
    }

    private function processTask(Task $task, SymfonyStyle $io, bool $dryRun): bool
    {
        $processor = $this->processors[$task->getType()] ?? null;

        if (!$processor) {
            $io->error(\sprintf(
                'No processor found for task type "%s" (Task ID: %d)',
                $task->getType(),
                $task->getId()
            ));

            if (!$dryRun) {
                $this->taskService->failTask(
                    $task,
                    \sprintf('No processor found for task type "%s"', $task->getType())
                );
            }

            return false;
        }

        $io->writeln(\sprintf(
            '<info>Processing task %d: %s</info> (Entity: %s, Priority: %d)',
            $task->getId(),
            $task->getType(),
            $task->getEntityName() ?: $task->getEntityMbid() ?: $task->getEntityId() ?: 'Unknown',
            $task->getPriority()
        ));

        if ($dryRun) {
            $io->writeln('<comment>  [DRY RUN] Would process task with processor: ' . $processor::class . '</comment>');

            return true;
        }

        try {
            // If a stop was requested, cancel before starting
            if ($this->shouldStop) {
                if (!$task->isFinalized()) {
                    $this->taskService->cancelTask($task, 'Task cancelled before start due to stop request');
                }
                $io->writeln(\sprintf('<comment>  ! Task %d cancelled before start</comment>', $task->getId()));

                return false;
            }
            // Start the task (if not already claimed as running)
            $this->currentTask = $task;
            if ($this->shouldStop) {
                // Race condition safety: re-check
                if (!$task->isFinalized()) {
                    $this->taskService->cancelTask($task, 'Task cancelled before start due to stop request');
                }
                $io->writeln(\sprintf('<comment>  ! Task %d cancelled before start</comment>', $task->getId()));

                return false;
            }
            if (Task::STATUS_RUNNING !== $task->getStatus()) {
                $this->taskService->startTask($task);
            }

            // Process the task
            $result = $processor->process($task);

            // Complete or fail the task based on result
            if ($this->shouldStop || $task->isFinalized()) {
                // Preserve cancellation/final state if a stop was requested during processing
                $io->writeln(\sprintf('<comment>  ! Task %d finalization skipped (stop requested or already finalized)</comment>', $task->getId()));

                return false;
            }

            if ($result->isSuccess()) {
                $this->taskService->completeTask($task, $result->getMetadata());
                $io->writeln(\sprintf('<comment>  ✓ Task %d completed successfully</comment>', $task->getId()));

                if ($result->getMessage()) {
                    $io->writeln('    ' . $result->getMessage());
                }
            } else {
                $this->taskService->failTask($task, $result->getErrorMessage());
                $io->writeln(\sprintf('<error>  ✗ Task %d failed: %s</error>', $task->getId(), $result->getErrorMessage()));
            }

            return $result->isSuccess();
        } catch (Throwable $e) {
            if (!$task->isFinalized()) {
                $this->taskService->failTask($task, $e->getMessage(), $e);
            }

            $io->writeln(\sprintf(
                '<error>  ✗ Task %d failed with exception: %s</error>',
                $task->getId(),
                $e->getMessage()
            ));

            $this->logger->error('Task processing failed', [
                'taskId' => $task->getId(),
                'taskType' => $task->getType(),
                'exception' => $e,
            ]);

            // Try to recreate the task for requeuing
            $this->recreateFailedTask($task, $e, $io);

            return false;
        } finally {
            $this->currentTask = null;
        }
    }

    /**
     * Recreate a failed task for requeuing.
     */
    private function recreateFailedTask(Task $failedTask, Throwable $exception, SymfonyStyle $io): void
    {
        try {
            // Check if entity manager is closed and reopen if necessary
            if (!$this->entityManager->isOpen()) {
                $this->logger->warning('Entity manager was closed, reopening...', [
                    'taskId' => $failedTask->getId(),
                    'taskType' => $failedTask->getType(),
                ]);

                // Get a new entity manager using the registry
                $newEntityManager = $this->getNewEntityManager();

                // Update the entity manager reference
                $this->entityManager = $newEntityManager;
            }

            // Check if we should recreate this task (avoid infinite loops)
            if ($this->shouldSkipTaskRecreation($failedTask)) {
                $io->writeln(\sprintf(
                    '<comment>  ⚠ Task %d skipped for recreation (already recreated too many times)</comment>',
                    $failedTask->getId()
                ));

                return;
            }

            // Prepare metadata for the new task with incremented recreation count
            $metadata = $failedTask->getMetadata() ?? [];
            $recreationCount = $metadata['recreation_count'] ?? 0;
            $metadata['recreation_count'] = $recreationCount + 1;
            $metadata['original_task_id'] = $failedTask->getId();
            $metadata['recreation_reason'] = $exception->getMessage();

            // Create a new task with the same parameters
            $newTask = $this->taskService->createTask(
                $failedTask->getType(),
                $failedTask->getEntityMbid(),
                $failedTask->getEntityId(),
                $failedTask->getEntityName(),
                $metadata,
                $failedTask->getPriority()
            );

            $io->writeln(\sprintf(
                '<info>  ↻ Task %d recreated as task %d for requeuing</info>',
                $failedTask->getId(),
                $newTask->getId()
            ));

            $this->logger->info('Failed task recreated for requeuing', [
                'originalTaskId' => $failedTask->getId(),
                'newTaskId' => $newTask->getId(),
                'taskType' => $failedTask->getType(),
                'exception' => $exception->getMessage(),
            ]);
        } catch (Throwable $recreateException) {
            $io->writeln(\sprintf(
                '<error>  ✗ Failed to recreate task %d: %s</error>',
                $failedTask->getId(),
                $recreateException->getMessage()
            ));

            $this->logger->error('Failed to recreate task for requeuing', [
                'originalTaskId' => $failedTask->getId(),
                'taskType' => $failedTask->getType(),
                'originalException' => $exception->getMessage(),
                'recreateException' => $recreateException->getMessage(),
            ]);
        }
    }

    /**
     * Check if a task should be skipped for recreation to avoid infinite loops.
     */
    private function shouldSkipTaskRecreation(Task $failedTask): bool
    {
        // Check if this task has been recreated too many times
        $metadata = $failedTask->getMetadata() ?? [];
        $recreationCount = $metadata['recreation_count'] ?? 0;

        // Limit recreations to prevent infinite loops
        $maxRecreations = 3;

        if ($recreationCount >= $maxRecreations) {
            $this->logger->warning('Task skipped for recreation - maximum attempts reached', [
                'taskId' => $failedTask->getId(),
                'taskType' => $failedTask->getType(),
                'recreationCount' => $recreationCount,
                'maxRecreations' => $maxRecreations,
            ]);

            return true;
        }

        return false;
    }

    public function getSubscribedSignals(): array
    {
        // Handle Ctrl+C (SIGINT) and termination (SIGTERM)
        return \defined('SIGINT') && \defined('SIGTERM') ? [\SIGINT, \SIGTERM] : [];
    }

    public function handleSignal(int $signal, int|false $previousExitCode = 0): false|int
    {
        $this->shouldStop = true;

        $signalName = match ($signal) {
            \defined('SIGINT') ? \SIGINT : -1 => 'SIGINT',
            \defined('SIGTERM') ? \SIGTERM : -1 => 'SIGTERM',
            default => (string) $signal,
        };

        try {
            if ($this->currentTask instanceof Task && !$this->currentTask->isFinalized()) {
                $this->taskService->cancelTask($this->currentTask, 'Task cancelled by signal: ' . $signalName);
                $this->logger->warning('Task cancelled due to signal', [
                    'signal' => $signalName,
                    'taskId' => $this->currentTask->getId(),
                    'taskType' => $this->currentTask->getType(),
                ]);
            }
        } catch (Throwable $e) {
            // Try to reopen entity manager if it's closed
            try {
                $this->ensureEntityManagerIsOpen();
                // Retry the cancellation
                if ($this->currentTask instanceof Task && !$this->currentTask->isFinalized()) {
                    $this->taskService->cancelTask($this->currentTask, 'Task cancelled by signal: ' . $signalName);
                }
            } catch (Throwable $retryException) {
                $this->logger->error('Failed to cancel task during signal handling even after reopening entity manager', [
                    'signal' => $signalName,
                    'taskId' => $this->currentTask?->getId(),
                    'taskType' => $this->currentTask?->getType(),
                    'originalException' => $e->getMessage(),
                    'retryException' => $retryException->getMessage(),
                ]);
            }

            return $signal;
        }

        return $signal;
    }

    /**
     * Register shutdown function to handle fatal errors.
     */
    private function registerShutdownFunction(): void
    {
        register_shutdown_function(function () {
            $error = error_get_last();
            if (null === $error) {
                return;
            }

            try {
                if ($this->currentTask instanceof Task && Task::STATUS_RUNNING === $this->currentTask->getStatus()) {
                    $message = \sprintf(
                        'Task failed due to fatal error: %s in %s:%d',
                        $error['message'] ?? 'unknown',
                        $error['file'] ?? 'unknown',
                        $error['line'] ?? 0
                    );

                    // Try to ensure entity manager is open before marking task as failed
                    try {
                        if (!$this->entityManager->isOpen()) {
                            $this->entityManager = $newEntityManager;
                        }

                        $this->taskService->failTask($this->currentTask, $message);
                        $this->logger->error($message, [
                            'taskId' => $this->currentTask->getId(),
                            'taskType' => $this->currentTask->getType(),
                        ]);
                    } catch (Throwable $emException) {
                        $this->logger->error('Failed to mark task as failed during shutdown due to entity manager issues', [
                            'taskId' => $this->currentTask->getId(),
                            'taskType' => $this->currentTask->getType(),
                            'originalMessage' => $message,
                            'entityManagerException' => $emException->getMessage(),
                        ]);
                    }
                }
            } catch (Throwable $e) {
                // Best-effort only; ignore any exceptions during shutdown
                $this->logger->error('Exception during shutdown function', [
                    'exception' => $e->getMessage(),
                ]);
            }
        });
    }

    /**
     * Parse input options and return as array.
     */
    private function parseInputOptions(InputInterface $input): array
    {
        return [
            'limit' => (int) $input->getOption('limit'),
            'typeFilter' => $input->getOption('type'),
            'maxExecutionTime' => (int) $input->getOption('max-execution-time'),
            'dryRun' => $input->getOption('dry-run'),
            'single' => $input->getOption('single'),
            'continuous' => $input->getOption('continuous'),
            'sleepInterval' => (int) $input->getOption('sleep-interval'),
        ];
    }

    /**
     * Display mode notes to user.
     */
    private function displayModeNotes(SymfonyStyle $io, array $options): void
    {
        if ($options['continuous']) {
            $io->note('Running in continuous mode. Press Ctrl+C to stop.');
        }

        if ($options['dryRun']) {
            $io->note('Running in dry-run mode. No tasks will actually be processed.');
        }
    }

    /**
     * Cancel stale running tasks.
     */
    private function cancelStaleTasks(SymfonyStyle $io): void
    {
        $staleCancelled = $this->taskService->cancelStaleRunningTasks();
        if ($staleCancelled > 0) {
            $io->warning("Cancelled {$staleCancelled} stale running tasks.");
        }
    }

    /**
     * Handle no tasks found in continuous mode.
     */
    private function handleNoTasksInContinuousMode(SymfonyStyle $io, array $options, int $startTime): void
    {
        $io->writeln(\sprintf('<comment>No pending tasks found. Sleeping for %d seconds...</comment>', $options['sleepInterval']));
        sleep($options['sleepInterval']);

        // Check execution time limit
        if (time() - $startTime >= $options['maxExecutionTime']) {
            $io->warning('Maximum execution time reached. Stopping.');
        }
    }

    /**
     * Process a batch of tasks.
     */
    private function processTaskBatch(array $tasks, SymfonyStyle $io, array $options, int $startTime, bool &$hasProcessedAny): int
    {
        $processedCount = 0;

        foreach ($tasks as $task) {
            if ($this->shouldStop) {
                $io->warning('Stop requested. Exiting task processing loop.');

                break;
            }

            // Check execution time limit
            if (time() - $startTime >= $options['maxExecutionTime']) {
                $io->warning('Maximum execution time reached. Stopping.');

                break;
            }

            // Ensure entity manager is open before processing each task
            try {
                $this->ensureEntityManagerIsOpen();
            } catch (Throwable $e) {
                $io->error('Failed to ensure entity manager is open: ' . $e->getMessage());
                break;
            }

            $success = $this->processTask($task, $io, $options['dryRun']);

            if ($success) {
                ++$processedCount;
                $hasProcessedAny = true;
            }

            if ($options['single']) {
                break;
            }
        }

        return $processedCount;
    }

    /**
     * Handle continuous mode sleep.
     */
    private function handleContinuousModeSleep(bool $hasProcessedAny, array $options): void
    {
        // In continuous mode, sleep a bit before next iteration if no tasks were processed
        if (!$hasProcessedAny && $options['continuous']) {
            sleep($options['sleepInterval']);
        }
    }

    /**
     * Display completion message.
     */
    private function displayCompletionMessage(SymfonyStyle $io, int $processedCount, int $startTime): void
    {
        $io->success(\sprintf(
            'Task processing completed. Processed %d task(s) in %d seconds.',
            $processedCount,
            time() - $startTime
        ));
    }

    /**
     * Get a new entity manager instance.
     */
    private function getNewEntityManager(): EntityManagerInterface
    {
        $objectManager = $this->managerRegistry->resetManager();

        if (!$objectManager instanceof EntityManagerInterface) {
            throw new LogicException('Wrong ObjectManager');
        }

        return $objectManager;
    }

    /**
     * Ensure the entity manager is open. If not, try to reopen it.
     */
    private function ensureEntityManagerIsOpen(): void
    {
        if (!$this->entityManager->isOpen()) {
            $this->logger->warning('Entity manager was closed, reopening...');
            try {
                $newEntityManager = $this->getNewEntityManager();
                $this->entityManager = $newEntityManager;
                $this->logger->info('Entity manager reopened successfully.');
            } catch (Throwable $e) {
                $this->logger->error('Failed to reopen entity manager', [
                    'exception' => $e->getMessage(),
                ]);
                throw $e; // Re-throw to be caught by the main execution loop
            }
        }
    }
}
