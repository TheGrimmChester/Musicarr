<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Task;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Find an existing active task by unique key to prevent duplicates.
     */
    public function findActiveByUniqueKey(string $uniqueKey): ?Task
    {
        /** @var Task|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.uniqueKey = :uniqueKey')
            ->andWhere('t.status IN (:activeStatuses)')
            ->setParameter('uniqueKey', $uniqueKey)
            ->setParameter('activeStatuses', [Task::STATUS_PENDING, Task::STATUS_RUNNING])
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }

    /**
     * Find an existing active task by type and entity identifiers.
     */
    public function findActiveByTypeAndEntity(string $type, ?string $entityMbid = null, ?int $entityId = null, ?string $entityName = null): ?Task
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->andWhere('t.status IN (:activeStatuses)')
            ->setParameter('type', $type)
            ->setParameter('activeStatuses', [Task::STATUS_PENDING, Task::STATUS_RUNNING]);

        if ($entityMbid) {
            $queryBuilder->andWhere('t.entityMbid = :entityMbid')
                ->setParameter('entityMbid', $entityMbid);
        }

        if ($entityId) {
            $queryBuilder->andWhere('t.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        }

        if ($entityName) {
            $queryBuilder->andWhere('t.entityName = :entityName')
                ->setParameter('entityName', $entityName);
        }

        /** @var Task|null $result */
        $result = $queryBuilder->getQuery()->getOneOrNullResult();

        return $result;
    }

    /**
     * Get all pending tasks ordered by priority and creation date.
     */
    public function findPendingTasks(?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', Task::STATUS_PENDING)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC');

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var Task[] $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * Get all running tasks.
     */
    public function findRunningTasks(): array
    {
        /** @var Task[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', Task::STATUS_RUNNING)
            ->orderBy('t.startedAt', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get tasks by status.
     */
    public function findByStatus(string $status, ?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', $status)
            ->orderBy('t.createdAt', 'DESC');

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var Task[] $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * Get tasks by type.
     */
    public function findByType(string $type, ?int $limit = null): array
    {
        $queryBuilder = $this->createQueryBuilder('t')
            ->where('t.type = :type')
            ->setParameter('type', $type)
            ->orderBy('t.createdAt', 'DESC');

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        /** @var Task[] $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * Get recent completed tasks.
     */
    public function findRecentCompleted(int $hours = 24, int $limit = 50): array
    {
        $since = new DateTime();
        $since->modify("-{$hours} hours");

        /** @var Task[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.completedAt >= :since')
            ->setParameter('status', Task::STATUS_COMPLETED)
            ->setParameter('since', $since)
            ->orderBy('t.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get recent failed tasks.
     */
    public function findRecentFailed(int $hours = 24, int $limit = 50): array
    {
        $since = new DateTime();
        $since->modify("-{$hours} hours");

        /** @var Task[] $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->andWhere('t.completedAt >= :since')
            ->setParameter('status', Task::STATUS_FAILED)
            ->setParameter('since', $since)
            ->orderBy('t.completedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get task statistics.
     */
    public function getTaskStatistics(): array
    {
        /** @var array<array{status: string, count: string}> $result */
        $result = $this->createQueryBuilder('t')
            ->select('t.status, COUNT(t.id) as count')
            ->groupBy('t.status')
            ->getQuery()
            ->getResult();

        $stats = [
            Task::STATUS_PENDING => 0,
            Task::STATUS_RUNNING => 0,
            Task::STATUS_COMPLETED => 0,
            Task::STATUS_FAILED => 0,
            Task::STATUS_CANCELLED => 0,
        ];

        foreach ($result as $row) {
            $stats[$row['status']] = (int) $row['count'];
        }

        return $stats;
    }

    /**
     * Find tasks by multiple filters.
     */
    public function findByFilters(array $filters, ?int $limit = null, int $offset = 0): array
    {
        $queryBuilder = $this->createQueryBuilder('t');

        // Apply filters
        if (!empty($filters['status'])) {
            $queryBuilder->andWhere('t.status = :status')
                ->setParameter('status', $filters['status']);
        }

        if (!empty($filters['type'])) {
            $queryBuilder->andWhere('t.type = :type')
                ->setParameter('type', $filters['type']);
        }

        if (isset($filters['priority']) && '' !== $filters['priority']) {
            $queryBuilder->andWhere('t.priority = :priority')
                ->setParameter('priority', (int) $filters['priority']);
        }

        if (!empty($filters['entity_name'])) {
            $queryBuilder->andWhere('t.entityName LIKE :entityName')
                ->setParameter('entityName', '%' . $filters['entity_name'] . '%');
        }

        if (!empty($filters['created_after'])) {
            $queryBuilder->andWhere('t.createdAt >= :createdAfter')
                ->setParameter('createdAfter', new DateTime($filters['created_after']));
        }

        if (!empty($filters['created_before'])) {
            $queryBuilder->andWhere('t.createdAt <= :createdBefore')
                ->setParameter('createdBefore', new DateTime($filters['created_before'] . ' 23:59:59'));
        }

        // Apply sorting
        $sortField = $filters['sort'] ?? 'createdAt';
        $sortOrder = mb_strtoupper($filters['order'] ?? 'DESC');

        $validSortFields = ['createdAt', 'startedAt', 'completedAt', 'priority', 'status', 'type'];
        if (!\in_array($sortField, $validSortFields, true)) {
            $sortField = 'createdAt';
        }

        if (!\in_array($sortOrder, ['ASC', 'DESC'], true)) {
            $sortOrder = 'DESC';
        }

        $queryBuilder->orderBy('t.' . $sortField, $sortOrder);

        // Add secondary sort by ID for consistency
        if ('createdAt' !== $sortField) {
            $queryBuilder->addOrderBy('t.createdAt', 'DESC');
        }

        if ($limit) {
            $queryBuilder->setMaxResults($limit);
        }

        if ($offset > 0) {
            $queryBuilder->setFirstResult($offset);
        }

        /** @var Task[] $result */
        $result = $queryBuilder->getQuery()->getResult();

        return $result;
    }

    /**
     * Get unique entity names for filtering.
     */
    public function getUniqueEntityNames(int $limit = 50): array
    {
        /** @var array<array{entityName: string}> $result */
        $result = $this->createQueryBuilder('t')
            ->select('DISTINCT t.entityName')
            ->where('t.entityName IS NOT NULL')
            ->andWhere('t.entityName != :empty')
            ->setParameter('empty', '')
            ->orderBy('t.entityName', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_column($result, 'entityName');
    }

    /**
     * Clean up old completed and failed tasks.
     */
    public function cleanupOldTasks(int $daysOld = 30): int
    {
        $cutoffDate = new DateTime();
        $cutoffDate->modify("-{$daysOld} days");

        /** @var int $result */
        $result = $this->createQueryBuilder('t')
            ->delete()
            ->where('t.status IN (:finalStatuses)')
            ->andWhere('t.completedAt < :cutoffDate')
            ->setParameter('finalStatuses', [Task::STATUS_COMPLETED, Task::STATUS_FAILED, Task::STATUS_CANCELLED])
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        return $result;
    }

    /**
     * Cancel stale running tasks (running for too long).
     */
    public function cancelStaleRunningTasks(int $maxHours = 24): int
    {
        $cutoffDate = new DateTime();
        $cutoffDate->modify("-{$maxHours} hours");

        /** @var int $result */
        $result = $this->createQueryBuilder('t')
            ->update()
            ->set('t.status', ':cancelledStatus')
            ->set('t.completedAt', ':now')
            ->set('t.errorMessage', ':errorMessage')
            ->where('t.status = :runningStatus')
            ->andWhere('t.startedAt < :cutoffDate')
            ->setParameter('cancelledStatus', Task::STATUS_CANCELLED)
            ->setParameter('now', new DateTime())
            ->setParameter('errorMessage', 'Task cancelled due to timeout')
            ->setParameter('runningStatus', Task::STATUS_RUNNING)
            ->setParameter('cutoffDate', $cutoffDate)
            ->getQuery()
            ->execute();

        return $result;
    }

    /**
     * Find tasks for a specific entity (by MBID or ID).
     */
    public function findForEntity(?string $entityMbid = null, ?int $entityId = null): array
    {
        $queryBuilder = $this->createQueryBuilder('t');

        if ($entityMbid) {
            $queryBuilder->where('t.entityMbid = :entityMbid')
                ->setParameter('entityMbid', $entityMbid);
        } elseif ($entityId) {
            $queryBuilder->where('t.entityId = :entityId')
                ->setParameter('entityId', $entityId);
        } else {
            return [];
        }

        /** @var Task[] $result */
        $result = $queryBuilder->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Get next task to process (highest priority pending task).
     */
    public function getNextTask(): ?Task
    {
        /** @var Task|null $result */
        $result = $this->createQueryBuilder('t')
            ->where('t.status = :status')
            ->setParameter('status', Task::STATUS_PENDING)
            ->orderBy('t.priority', 'DESC')
            ->addOrderBy('t.createdAt', 'ASC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        return $result;
    }
}
