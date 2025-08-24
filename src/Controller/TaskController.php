<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Task;
use App\Task\TaskFactory;
use DateTime;
use Exception;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/tasks')]
class TaskController extends AbstractController
{
    public function __construct(
        private TaskFactory $taskService
    ) {
    }

    #[Route('', name: 'tasks_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Extract all possible filters
        $filters = [
            'status' => $request->query->get('status'),
            'type' => $request->query->get('type'),
            'priority' => $request->query->get('priority'),
            'entity_name' => $request->query->get('entity_name'),
            'created_after' => $request->query->get('created_after'),
            'created_before' => $request->query->get('created_before'),
            'sort' => $request->query->get('sort', 'created_at'),
            'order' => $request->query->get('order', 'desc'),
        ];

        // Clean up empty filters
        $activeFilters = array_filter($filters, fn ($value) => null !== $value && '' !== $value);

        // Get tasks based on filters
        $tasks = $this->taskService->getTaskRepository()->findByFilters($activeFilters, $limit + 1, $offset);

        $hasNextPage = \count($tasks) > $limit;
        if ($hasNextPage) {
            array_pop($tasks); // Remove the extra task used for pagination check
        }

        $statistics = $this->taskService->getTaskStatistics();

        // Get priority options for filter
        $priorityOptions = [
            5 => 'Very High (5)',
            4 => 'High (4)',
            3 => 'Normal (3)',
            2 => 'Low (2)',
            1 => 'Very Low (1)',
            0 => 'Lowest (0)',
        ];

        // Get unique entity names for filter
        $entityNames = $this->taskService->getTaskRepository()->getUniqueEntityNames();

        return $this->render('tasks/index.html.twig', [
            'tasks' => $tasks,
            'statistics' => $statistics,
            'currentPage' => $page,
            'hasNextPage' => $hasNextPage,
            'hasPrevPage' => $page > 1,
            'filters' => $filters,
            'activeFilters' => $activeFilters,
            'taskTypes' => Task::getTaskTypes(),
            'taskStatuses' => Task::getTaskStatuses(),
            'priorityOptions' => $priorityOptions,
            'entityNames' => $entityNames,
            'prevPageUrl' => $this->buildPaginationUrl($page - 1, $filters),
            'nextPageUrl' => $this->buildPaginationUrl($page + 1, $filters),
            'quickFilterUrls' => [
                'pending' => $this->buildQuickFilterUrl('status', 'pending', $filters),
                'running' => $this->buildQuickFilterUrl('status', 'running', $filters),
                'failed' => $this->buildQuickFilterUrl('status', 'failed', $filters),
                'highPriority' => $this->buildQuickFilterUrl('priority', '5', $filters),
                'today' => $this->buildQuickFilterUrl('created_after', (new DateTime())->format('Y-m-d'), $filters),
                'byPriority' => $this->buildQuickFilterUrl('sort', 'priority', array_merge($filters, ['order' => 'desc'])),
            ],
            'filterRemovalUrls' => array_reduce(array_keys($activeFilters), function ($carry, $filterKey) use ($filters) {
                if (!\in_array($filterKey, ['sort', 'order'], true)) {
                    $carry[$filterKey] = $this->buildFilterRemovalUrl($filterKey, $filters);
                }

                return $carry;
            }, []),
        ]);
    }

    #[Route('/create', name: 'tasks_create', methods: ['GET', 'POST'])]
    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            try {
                /** @var array{type?: string, entity_mbid?: string, entity_id?: string, entity_name?: string, priority?: string} $data */
                $data = $request->request->all();

                $type = $data['type'] ?? '';
                $entityMbid = $data['entity_mbid'] ?: null;
                $entityId = $data['entity_id'] ? (int) $data['entity_id'] : null;
                $entityName = $data['entity_name'] ?: null;
                $priority = (int) ($data['priority'] ?? 0);

                if (!$type) {
                    throw new InvalidArgumentException('Task type is required');
                }

                $task = $this->taskService->createTask(
                    $type,
                    $entityMbid,
                    $entityId,
                    $entityName,
                    null,
                    $priority
                );

                $this->addFlash('success', \sprintf('Task "%s" created successfully (ID: %d)', $type, $task->getId()));

                return $this->redirectToRoute('tasks_show', ['id' => $task->getId()]);
            } catch (Exception $e) {
                $this->addFlash('error', 'Failed to create task: ' . $e->getMessage());
            }
        }

        return $this->render('tasks/create.html.twig', [
            'taskTypes' => Task::getTaskTypes(),
        ]);
    }

    #[Route('/{id}', name: 'tasks_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): Response
    {
        $task = $this->taskService->getTaskRepository()->find($id);

        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        // Get related tasks for the same entity
        $relatedTasks = [];
        if ($task->getEntityMbid()) {
            $relatedTasks = $this->taskService->getTasksForEntity($task->getEntityMbid());
        } elseif ($task->getEntityId()) {
            $relatedTasks = $this->taskService->getTasksForEntity(null, $task->getEntityId());
        }

        // Remove current task from related tasks
        $relatedTasks = array_filter($relatedTasks, fn ($t) => $t->getId() !== $task->getId());

        return $this->render('tasks/show.html.twig', [
            'task' => $task,
            'relatedTasks' => $relatedTasks,
        ]);
    }

    #[Route('/{id}/cancel', name: 'tasks_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): Response
    {
        $task = $this->taskService->getTaskRepository()->find($id);

        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        try {
            $reason = $request->request->get('reason', 'Cancelled by user');
            $this->taskService->cancelTask($task, (string) $reason);
            $this->addFlash('success', 'Task cancelled successfully');
        } catch (Exception $e) {
            $this->addFlash('error', 'Failed to cancel task: ' . $e->getMessage());
        }

        return $this->redirectToRoute('tasks_show', ['id' => $id]);
    }

    #[Route('/{id}/retry', name: 'tasks_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $id): Response
    {
        $task = $this->taskService->getTaskRepository()->find($id);

        if (!$task) {
            throw $this->createNotFoundException('Task not found');
        }

        try {
            $newTask = $this->taskService->retryFailedTask($task);
            $this->addFlash('success', \sprintf('Task retried successfully (New task ID: %d)', $newTask->getId()));

            return $this->redirectToRoute('tasks_show', ['id' => $newTask->getId()]);
        } catch (Exception $e) {
            $this->addFlash('error', 'Failed to retry task: ' . $e->getMessage());

            return $this->redirectToRoute('tasks_show', ['id' => $id]);
        }
    }

    #[Route('/ajax/statistics', name: 'tasks_ajax_statistics', methods: ['GET'])]
    public function ajaxStatistics(): JsonResponse
    {
        $statistics = $this->taskService->getTaskStatistics();

        return new JsonResponse($statistics);
    }

    #[Route('/ajax/recent', name: 'tasks_ajax_recent', methods: ['GET'])]
    public function ajaxRecent(Request $request): JsonResponse
    {
        $limit = $request->query->getInt('limit', 10);

        $recent = $this->taskService->getTaskRepository()->findBy(
            [],
            ['createdAt' => 'DESC'],
            $limit
        );

        $tasksData = array_map(function (Task $task) {
            return [
                'id' => $task->getId(),
                'type' => $task->getType(),
                'status' => $task->getStatus(),
                'entityName' => $task->getEntityName(),
                'createdAt' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
                'duration' => $task->getDuration(),
                'url' => $this->generateUrl('tasks_show', ['id' => $task->getId()]),
            ];
        }, $recent);

        return new JsonResponse($tasksData);
    }

    #[Route('/ajax/{id}/status', name: 'tasks_ajax_status', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function ajaxStatus(int $id): JsonResponse
    {
        $task = $this->taskService->getTaskRepository()->find($id);

        if (!$task) {
            return new JsonResponse(['error' => 'Task not found'], 404);
        }

        return new JsonResponse([
            'id' => $task->getId(),
            'status' => $task->getStatus(),
            'progress' => $task->getMetadata()['progress'] ?? null,
            'duration' => $task->getDuration(),
            'errorMessage' => $task->getErrorMessage(),
            'isActive' => $task->isActive(),
            'isFinalized' => $task->isFinalized(),
        ]);
    }

    #[Route('/cleanup', name: 'tasks_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): Response
    {
        try {
            $daysOld = $request->request->getInt('days_old', 30);
            $deletedCount = $this->taskService->cleanupOldTasks($daysOld);

            $this->addFlash('success', \sprintf('Successfully deleted %d old tasks', $deletedCount));
        } catch (Exception $e) {
            $this->addFlash('error', 'Failed to cleanup tasks: ' . $e->getMessage());
        }

        return $this->redirectToRoute('tasks_index');
    }

    #[Route('/bulk-action', name: 'tasks_bulk_action', methods: ['POST'])]
    public function bulkAction(Request $request): Response
    {
        $action = $request->request->get('action');
        $taskIds = $request->request->all('task_ids');

        if (empty($taskIds)) {
            $this->addFlash('error', 'No tasks selected');

            return $this->redirectToRoute('tasks_index');
        }

        $successCount = 0;
        $errorCount = 0;

        foreach ($taskIds as $taskId) {
            try {
                $task = $this->taskService->getTaskRepository()->find((int) $taskId);
                if (!$task) {
                    continue;
                }

                switch ($action) {
                    case 'cancel':
                        if ($task->isActive()) {
                            $this->taskService->cancelTask($task, 'Bulk cancellation');
                            ++$successCount;
                        }

                        break;
                    case 'retry':
                        if (Task::STATUS_FAILED === $task->getStatus()) {
                            $this->taskService->retryFailedTask($task);
                            ++$successCount;
                        }

                        break;
                }
            } catch (Exception $e) {
                ++$errorCount;
            }
        }

        if ($successCount > 0) {
            $this->addFlash('success', \sprintf('Successfully processed %d tasks', $successCount));
        }
        if ($errorCount > 0) {
            $this->addFlash('error', \sprintf('Failed to process %d tasks', $errorCount));
        }

        return $this->redirectToRoute('tasks_index');
    }

    /**
     * Build pagination URL with filters preserved.
     *
     * @param array<string, mixed> $filters
     */
    private function buildPaginationUrl(int $page, array $filters): string
    {
        $params = ['page' => $page];

        // Add non-empty filters to the URL
        foreach ($filters as $key => $value) {
            if (null !== $value && '' !== $value) {
                $params[$key] = $value;
            }
        }

        return $this->generateUrl('tasks_index', $params);
    }

    /**
     * Build quick filter URL with specific filter value.
     *
     * @param array<string, mixed> $currentFilters
     */
    private function buildQuickFilterUrl(string $filterKey, string $filterValue, array $currentFilters = []): string
    {
        $params = [$filterKey => $filterValue];

        // Preserve other non-empty filters (except the one being set)
        foreach ($currentFilters as $key => $value) {
            if ($key !== $filterKey && null !== $value && '' !== $value) {
                $params[$key] = $value;
            }
        }

        return $this->generateUrl('tasks_index', $params);
    }

    /**
     * Build URL with a specific filter removed.
     *
     * @param array<string, mixed> $currentFilters
     */
    private function buildFilterRemovalUrl(string $filterKeyToRemove, array $currentFilters): string
    {
        $params = [];

        // Add all filters except the one to remove
        foreach ($currentFilters as $key => $value) {
            if ($key !== $filterKeyToRemove && null !== $value && '' !== $value) {
                $params[$key] = $value;
            }
        }

        return $this->generateUrl('tasks_index', $params);
    }
}
