<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Task;
use App\Task\TaskFactory;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/tasks')]
class TaskApiController extends AbstractController
{
    public function __construct(
        private TaskFactory $taskService
    ) {
    }

    #[Route('', name: 'api_tasks_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $status = $request->query->get('status');
            $type = $request->query->get('type');
            $limit = $request->query->getInt('limit', 50);

            if ($status) {
                $tasks = $this->taskService->getTaskRepository()->findByStatus($status, $limit);
            } elseif ($type) {
                $tasks = $this->taskService->getTaskRepository()->findByType($type, $limit);
            } else {
                $tasks = $this->taskService->getTaskRepository()->findBy([], ['createdAt' => 'DESC'], $limit);
            }

            $tasksData = array_map(function (Task $task) {
                return [
                    'id' => $task->getId(),
                    'type' => $task->getType(),
                    'status' => $task->getStatus(),
                    'entityMbid' => $task->getEntityMbid(),
                    'entityId' => $task->getEntityId(),
                    'entityName' => $task->getEntityName(),
                    'metadata' => $task->getMetadata(),
                    'errorMessage' => $task->getErrorMessage(),
                    'createdAt' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'startedAt' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                    'completedAt' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'priority' => $task->getPriority(),
                    'uniqueKey' => $task->getUniqueKey(),
                    'duration' => $task->getDuration(),
                ];
            }, $tasks);

            return new JsonResponse([
                'success' => true,
                'data' => $tasksData,
                'total' => \count($tasksData),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/statistics', name: 'api_tasks_statistics', methods: ['GET'])]
    public function statistics(): JsonResponse
    {
        try {
            $stats = $this->taskService->getTaskStatistics();

            return new JsonResponse([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get task statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/pending', name: 'api_tasks_pending', methods: ['GET'])]
    public function pending(Request $request): JsonResponse
    {
        try {
            $limit = $request->query->getInt('limit', 50);
            $tasks = $this->taskService->getPendingTasks($limit);

            $tasksData = array_map(function (Task $task) {
                return [
                    'id' => $task->getId(),
                    'type' => $task->getType(),
                    'status' => $task->getStatus(),
                    'entityMbid' => $task->getEntityMbid(),
                    'entityId' => $task->getEntityId(),
                    'entityName' => $task->getEntityName(),
                    'priority' => $task->getPriority(),
                    'createdAt' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'uniqueKey' => $task->getUniqueKey(),
                ];
            }, $tasks);

            return new JsonResponse([
                'success' => true,
                'data' => $tasksData,
                'total' => \count($tasksData),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get pending tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/running', name: 'api_tasks_running', methods: ['GET'])]
    public function running(): JsonResponse
    {
        try {
            $tasks = $this->taskService->getRunningTasks();

            $tasksData = array_map(function (Task $task) {
                return [
                    'id' => $task->getId(),
                    'type' => $task->getType(),
                    'status' => $task->getStatus(),
                    'entityMbid' => $task->getEntityMbid(),
                    'entityId' => $task->getEntityId(),
                    'entityName' => $task->getEntityName(),
                    'startedAt' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                    'duration' => $task->getDuration(),
                    'uniqueKey' => $task->getUniqueKey(),
                ];
            }, $tasks);

            return new JsonResponse([
                'success' => true,
                'data' => $tasksData,
                'total' => \count($tasksData),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get running tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}', name: 'api_tasks_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskRepository()->find($id);

            if (!$task) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Task not found',
                ], 404);
            }

            $taskData = [
                'id' => $task->getId(),
                'type' => $task->getType(),
                'status' => $task->getStatus(),
                'entityMbid' => $task->getEntityMbid(),
                'entityId' => $task->getEntityId(),
                'entityName' => $task->getEntityName(),
                'metadata' => $task->getMetadata(),
                'errorMessage' => $task->getErrorMessage(),
                'createdAt' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
                'startedAt' => $task->getStartedAt()?->format('Y-m-d H:i:s'),
                'completedAt' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                'priority' => $task->getPriority(),
                'uniqueKey' => $task->getUniqueKey(),
                'duration' => $task->getDuration(),
                'isActive' => $task->isActive(),
                'isFinalized' => $task->isFinalized(),
            ];

            return new JsonResponse([
                'success' => true,
                'data' => $taskData,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to retrieve task: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/cancel', name: 'api_tasks_cancel', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function cancel(int $id, Request $request): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskRepository()->find($id);

            if (!$task) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Task not found',
                ], 404);
            }

            $data = json_decode($request->getContent(), true);
            $reason = $data['reason'] ?? 'Task cancelled via API';

            $this->taskService->cancelTask($task, $reason);

            return new JsonResponse([
                'success' => true,
                'message' => 'Task cancelled successfully',
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to cancel task: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/retry', name: 'api_tasks_retry', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function retry(int $id): JsonResponse
    {
        try {
            $task = $this->taskService->getTaskRepository()->find($id);

            if (!$task) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Task not found',
                ], 404);
            }

            $newTask = $this->taskService->retryFailedTask($task);

            return new JsonResponse([
                'success' => true,
                'message' => 'Task retried successfully',
                'data' => [
                    'newTaskId' => $newTask->getId(),
                ],
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to retry task: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/cleanup', name: 'api_tasks_cleanup', methods: ['POST'])]
    public function cleanup(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $daysOld = $data['daysOld'] ?? 30;

            $deletedCount = $this->taskService->cleanupOldTasks($daysOld);

            return new JsonResponse([
                'success' => true,
                'message' => 'Old tasks cleaned up successfully',
                'data' => [
                    'deletedCount' => $deletedCount,
                ],
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to cleanup tasks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/entity/{entityMbid}', name: 'api_tasks_by_entity_mbid', methods: ['GET'])]
    public function getByEntityMbid(string $entityMbid): JsonResponse
    {
        try {
            $tasks = $this->taskService->getTasksForEntity($entityMbid);

            $tasksData = array_map(function (Task $task) {
                return [
                    'id' => $task->getId(),
                    'type' => $task->getType(),
                    'status' => $task->getStatus(),
                    'entityMbid' => $task->getEntityMbid(),
                    'entityName' => $task->getEntityName(),
                    'createdAt' => $task->getCreatedAt()?->format('Y-m-d H:i:s'),
                    'completedAt' => $task->getCompletedAt()?->format('Y-m-d H:i:s'),
                    'errorMessage' => $task->getErrorMessage(),
                ];
            }, $tasks);

            return new JsonResponse([
                'success' => true,
                'data' => $tasksData,
                'total' => \count($tasksData),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get tasks for entity: ' . $e->getMessage(),
            ], 500);
        }
    }
}
