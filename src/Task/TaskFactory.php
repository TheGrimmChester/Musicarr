<?php

declare(strict_types=1);

namespace App\Task;

use App\Entity\Task;
use App\Repository\TaskRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use InvalidArgumentException;
use LogicException;
use Psr\Log\LoggerInterface;
use Throwable;

class TaskFactory
{
    public function __construct(
        private ManagerRegistry $managerRegistry,
        private TaskRepository $taskRepository,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get the current entity manager.
     */
    private function getEntityManager(): EntityManagerInterface
    {
        $entityManager = $this->managerRegistry->getManager();

        if (!$entityManager instanceof EntityManagerInterface) {
            throw new LogicException('Wrong ObjectManager');
        }

        return $entityManager;
    }

    /**
     * Create a new task if one doesn't already exist.
     */
    public function createTask(
        string $type,
        ?string $entityMbid = null,
        ?int $entityId = null,
        ?string $entityName = null,
        ?array $metadata = null,
        int $priority = 0
    ): Task {
        // Check if a similar active task already exists
        $existingTask = $this->findExistingActiveTask($type, $entityMbid, $entityId, $entityName);

        if ($existingTask) {
            $this->logger->info('Task already exists', [
                'type' => $type,
                'entityMbid' => $entityMbid,
                'entityId' => $entityId,
                'entityName' => $entityName,
                'existingTaskId' => $existingTask->getId(),
            ]);

            return $existingTask;
        }

        $task = new Task();
        $task->setType($type);
        $task->setEntityMbid($entityMbid);
        $task->setEntityId($entityId);
        $task->setEntityName($entityName);
        $task->setMetadata($metadata);
        $task->setPriority($priority);
        $task->generateUniqueKey();

        $this->getEntityManager()->persist($task);
        $this->getEntityManager()->flush();

        $this->logger->info('Created new task', [
            'taskId' => $task->getId(),
            'type' => $type,
            'uniqueKey' => $task->getUniqueKey(),
        ]);

        return $task;
    }

    /**
     * Create a task for syncing an artist (legacy alias for add).
     */
    public function createAddArtistTask(?string $artistMbid = null, ?string $artistName = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_SYNC_ARTIST,
            $artistMbid,
            null,
            $artistName,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for adding an album.
     */
    public function createAddAlbumTask(?string $albumMbid = null, ?string $albumName = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_ADD_ALBUM,
            $albumMbid,
            null,
            $albumName,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for updating an artist.
     */
    public function createUpdateArtistTask(?string $artistMbid = null, ?int $artistId = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_UPDATE_ARTIST,
            $artistMbid,
            $artistId,
            null,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for updating an album.
     */
    public function createUpdateAlbumTask(?string $albumMbid = null, ?int $albumId = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_UPDATE_ALBUM,
            $albumMbid,
            $albumId,
            null,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for syncing an artist.
     */
    public function createSyncArtistTask(?string $artistMbid = null, ?int $artistId = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_SYNC_ARTIST,
            $artistMbid,
            $artistId,
            null,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for syncing an album.
     */
    public function createSyncAlbumTask(?string $albumMbid = null, ?int $albumId = null, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_SYNC_ALBUM,
            $albumMbid,
            $albumId,
            null,
            $metadata,
            $priority
        );
    }

    /**
     * Check if a task already exists and is active.
     */
    public function hasActiveTask(
        string $type,
        ?string $entityMbid = null,
        ?int $entityId = null,
        ?string $entityName = null
    ): bool {
        return null !== $this->findExistingActiveTask($type, $entityMbid, $entityId, $entityName);
    }

    /**
     * Find an existing active task.
     */
    public function findExistingActiveTask(
        string $type,
        ?string $entityMbid = null,
        ?int $entityId = null,
        ?string $entityName = null
    ): ?Task {
        return $this->taskRepository->findActiveByTypeAndEntity($type, $entityMbid, $entityId, $entityName);
    }

    /**
     * Start a task (change status to running).
     */
    public function startTask(Task $task): Task
    {
        if (!$task->isActive()) {
            throw new InvalidArgumentException('Cannot start a task that is not in an active state');
        }

        $task->setStatus(Task::STATUS_RUNNING);
        $this->getEntityManager()->flush();

        $this->logger->info('Started task', [
            'taskId' => $task->getId(),
            'type' => $task->getType(),
        ]);

        return $task;
    }

    /**
     * Complete a task successfully.
     */
    public function completeTask(Task $task, ?array $resultMetadata = null): Task
    {
        $task->setStatus(Task::STATUS_COMPLETED);

        if ($resultMetadata) {
            $metadata = $task->getMetadata() ?? [];
            $metadata['result'] = $resultMetadata;
            $task->setMetadata($metadata);
        }

        $this->getEntityManager()->flush();

        $this->logger->info('Completed task', [
            'taskId' => $task->getId(),
            'type' => $task->getType(),
            'duration' => $task->getDuration(),
        ]);

        return $task;
    }

    /**
     * Fail a task with an error message.
     */
    public function failTask(Task $task, string $errorMessage, ?Throwable $exception = null): Task
    {
        $task->setStatus(Task::STATUS_FAILED);
        $task->setErrorMessage($errorMessage);

        if ($exception) {
            $metadata = $task->getMetadata() ?? [];
            $metadata['exception'] = [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ];
            $task->setMetadata($metadata);
        }

        $this->getEntityManager()->flush();

        $this->logger->error('Failed task', [
            'taskId' => $task->getId(),
            'type' => $task->getType(),
            'error' => $errorMessage,
            'exception' => $exception ? $exception->getMessage() : null,
        ]);

        return $task;
    }

    /**
     * Cancel a task.
     */
    public function cancelTask(Task $task, string $reason = 'Task cancelled'): Task
    {
        if ($task->isFinalized()) {
            throw new InvalidArgumentException('Cannot cancel a task that is already finalized');
        }

        $task->setStatus(Task::STATUS_CANCELLED);
        $task->setErrorMessage($reason);
        $this->getEntityManager()->flush();

        $this->logger->info('Cancelled task', [
            'taskId' => $task->getId(),
            'type' => $task->getType(),
            'reason' => $reason,
        ]);

        return $task;
    }

    /**
     * Get the next pending task to process.
     */
    public function getNextTask(): ?Task
    {
        return $this->taskRepository->getNextTask();
    }

    /**
     * Get all pending tasks.
     */
    public function getPendingTasks(?int $limit = null): array
    {
        return $this->taskRepository->findPendingTasks($limit);
    }

    /**
     * Get all running tasks.
     */
    public function getRunningTasks(): array
    {
        return $this->taskRepository->findRunningTasks();
    }

    /**
     * Get task statistics.
     */
    public function getTaskStatistics(): array
    {
        return $this->taskRepository->getTaskStatistics();
    }

    /**
     * Get the task repository.
     */
    public function getTaskRepository(): TaskRepository
    {
        return $this->taskRepository;
    }

    /**
     * Clean up old completed and failed tasks.
     */
    public function cleanupOldTasks(int $daysOld = 30): int
    {
        $deletedCount = $this->taskRepository->cleanupOldTasks($daysOld);

        $this->logger->info('Cleaned up old tasks', [
            'deletedCount' => $deletedCount,
            'daysOld' => $daysOld,
        ]);

        return $deletedCount;
    }

    /**
     * Cancel stale running tasks.
     */
    public function cancelStaleRunningTasks(int $maxHours = 24): int
    {
        $cancelledCount = $this->taskRepository->cancelStaleRunningTasks($maxHours);

        $this->logger->warning('Cancelled stale running tasks', [
            'cancelledCount' => $cancelledCount,
            'maxHours' => $maxHours,
        ]);

        return $cancelledCount;
    }

    /**
     * Get tasks for a specific entity.
     */
    public function getTasksForEntity(?string $entityMbid = null, ?int $entityId = null): array
    {
        return $this->taskRepository->findForEntity($entityMbid, $entityId);
    }

    /**
     * Find tasks by entity ID and type.
     */
    public function findTasksByEntityId(int $entityId, string $type): array
    {
        return $this->taskRepository->findBy([
            'entityId' => $entityId,
            'type' => $type,
        ], ['createdAt' => 'DESC']);
    }

    /**
     * Update task metadata.
     */
    public function updateTaskMetadata(Task $task, array $metadata): Task
    {
        $existingMetadata = $task->getMetadata() ?? [];
        $task->setMetadata(array_merge($existingMetadata, $metadata));
        $this->getEntityManager()->flush();

        return $task;
    }

    /**
     * Retry a failed task by creating a new one.
     */
    public function retryFailedTask(Task $failedTask): Task
    {
        if (Task::STATUS_FAILED !== $failedTask->getStatus()) {
            throw new InvalidArgumentException('Can only retry failed tasks');
        }

        return $this->createTask(
            $failedTask->getType(),
            $failedTask->getEntityMbid(),
            $failedTask->getEntityId(),
            $failedTask->getEntityName(),
            $failedTask->getMetadata(),
            $failedTask->getPriority()
        );
    }

    /**
     * Create a task for installing a plugin.
     */
    public function createPluginInstallTask(string $pluginName, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_PLUGIN_INSTALL,
            null,
            null,
            $pluginName,
            array_merge($metadata ?? [], ['plugin_name' => $pluginName]),
            $priority
        );
    }

    /**
     * Create a task for uninstalling a plugin.
     */
    public function createPluginUninstallTask(string $pluginName, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_PLUGIN_UNINSTALL,
            null,
            null,
            $pluginName,
            array_merge($metadata ?? [], ['plugin_name' => $pluginName]),
            $priority
        );
    }

    /**
     * Create a task for enabling a plugin.
     */
    public function createPluginEnableTask(string $pluginName, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_PLUGIN_ENABLE,
            null,
            null,
            $pluginName,
            array_merge($metadata ?? [], ['plugin_name' => $pluginName]),
            $priority
        );
    }

    /**
     * Create a task for disabling a plugin.
     */
    public function createPluginDisableTask(string $pluginName, ?array $metadata = null, int $priority = 0): Task
    {
        return $this->createTask(
            Task::TYPE_PLUGIN_DISABLE,
            null,
            null,
            $pluginName,
            array_merge($metadata ?? [], ['plugin_name' => $pluginName]),
            $priority
        );
    }

    /**
     * Create a task for upgrading a plugin.
     */
    public function createPluginUpgradeTask(string $pluginName, ?string $targetVersion = null, ?array $metadata = null, int $priority = 0): Task
    {
        $taskMetadata = array_merge($metadata ?? [], ['plugin_name' => $pluginName]);
        if ($targetVersion) {
            $taskMetadata['target_version'] = $targetVersion;
        }

        return $this->createTask(
            Task::TYPE_PLUGIN_UPGRADE,
            null,
            null,
            $pluginName,
            $taskMetadata,
            $priority
        );
    }

    /**
     * Create a task for installing a plugin from a remote repository.
     */
    public function createRemotePluginInstallTask(
        string $repositoryUrl,
        string $pluginName,
        ?string $branch = null,
        array $metadata = []
    ): Task {
        $installMetadata = [
            'repository_url' => $repositoryUrl,
            'plugin_name' => $pluginName,
            'branch' => $branch ?: 'main',
        ];

        $installMetadata = array_merge($installMetadata, $metadata);

        $task = new Task();
        $task->setType(Task::TYPE_REMOTE_PLUGIN_INSTALL);
        $task->setStatus(Task::STATUS_PENDING);
        $task->setPriority(Task::PRIORITY_NORMAL);
        $task->setMetadata($installMetadata);

        return $task;
    }

    public function createPluginReferenceChangeTask(
        string $pluginName,
        string $reference,
        string $referenceType = 'branch',
        array $metadata = []
    ): Task {
        $changeMetadata = [
            'plugin_name' => $pluginName,
            'reference' => $reference,
            'reference_type' => $referenceType,
        ];

        $changeMetadata = array_merge($changeMetadata, $metadata);

        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_REFERENCE_CHANGE);
        $task->setStatus(Task::STATUS_PENDING);
        $task->setPriority(Task::PRIORITY_NORMAL);
        $task->setMetadata($changeMetadata);

        return $task;
    }

    /**
     * Create a task for clearing the Symfony cache.
     */
    public function createCacheClearTask(?array $metadata = null, int $priority = Task::PRIORITY_NORMAL): Task
    {
        return $this->createTask(
            Task::TYPE_CACHE_CLEAR,
            null,
            null,
            null,
            $metadata,
            $priority
        );
    }

    /**
     * Create a task for running npm build.
     */
    public function createNpmBuildTask(?array $metadata = null, int $priority = Task::PRIORITY_NORMAL): Task
    {
        return $this->createTask(
            Task::TYPE_NPM_BUILD,
            null,
            null,
            null,
            $metadata,
            $priority
        );
    }
}
