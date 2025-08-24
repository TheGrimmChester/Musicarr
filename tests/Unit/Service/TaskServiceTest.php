<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Task;
use App\Repository\TaskRepository;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class TaskServiceTest extends TestCase
{
    private TaskFactory $taskService;
    private EntityManagerInterface|MockObject $entityManager;
    private TaskRepository|MockObject $taskRepository;
    private LoggerInterface|MockObject $logger;
    private ManagerRegistry|MockObject $managerRegistry;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->taskRepository = $this->createMock(TaskRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->managerRegistry = $this->createMock(ManagerRegistry::class);
        $this->managerRegistry
            ->method('getManager')
            ->willReturn($this->entityManager);

        $this->taskService = new TaskFactory(
            $this->managerRegistry,
            $this->taskRepository,
            $this->logger
        );
    }

    public function testCreateTaskWithNewTask(): void
    {
        $type = 'test_task';
        $entityMbid = 'test-mbid';
        $entityId = 123;
        $entityName = 'Test Entity';
        $metadata = ['key' => 'value'];
        $priority = 5;

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with($type, $entityMbid, $entityId, $entityName)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Task $task) use ($type, $entityMbid, $entityId, $entityName, $metadata, $priority) {
                return $task->getType() === $type
                       && $task->getEntityMbid() === $entityMbid
                       && $task->getEntityId() === $entityId
                       && $task->getEntityName() === $entityName
                       && $task->getMetadata() === $metadata
                       && $task->getPriority() === $priority;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Created new task', $this->callback(function (array $context) use ($type) {
                return isset($context['type']) && $context['type'] === $type
                       && isset($context['uniqueKey']);
            }));

        $result = $this->taskService->createTask($type, $entityMbid, $entityId, $entityName, $metadata, $priority);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($type, $result->getType());
        $this->assertEquals($entityMbid, $result->getEntityMbid());
        $this->assertEquals($entityId, $result->getEntityId());
        $this->assertEquals($entityName, $result->getEntityName());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals($priority, $result->getPriority());
    }

    public function testCreateTaskWithExistingTask(): void
    {
        $type = 'test_task';
        $entityMbid = 'test-mbid';
        $entityId = 123;
        $entityName = 'Test Entity';
        $metadata = ['key' => 'value'];
        $priority = 5;

        $existingTask = $this->createMock(Task::class);
        $existingTask->method('getId')->willReturn(456);

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with($type, $entityMbid, $entityId, $entityName)
            ->willReturn($existingTask);

        $this->entityManager
            ->expects($this->never())
            ->method('persist');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with('Task already exists', $this->callback(function (array $context) use ($type, $entityMbid, $entityId, $entityName) {
                return $context['type'] === $type
                       && $context['entityMbid'] === $entityMbid
                       && $context['entityId'] === $entityId
                       && $context['entityName'] === $entityName
                       && 456 === $context['existingTaskId'];
            }));

        $result = $this->taskService->createTask($type, $entityMbid, $entityId, $entityName, $metadata, $priority);

        $this->assertSame($existingTask, $result);
    }

    public function testCreateAddArtistTask(): void
    {
        $artistMbid = 'artist-mbid';
        $artistName = 'Artist Name';
        $metadata = ['genre' => 'Rock'];
        $priority = 3;

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with(Task::TYPE_SYNC_ARTIST, $artistMbid, null, $artistName)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createAddArtistTask($artistMbid, $artistName, $metadata, $priority);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(Task::TYPE_SYNC_ARTIST, $result->getType());
        $this->assertEquals($artistMbid, $result->getEntityMbid());
        $this->assertEquals($artistName, $result->getEntityName());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals($priority, $result->getPriority());
    }

    public function testCreateAddAlbumTask(): void
    {
        $albumMbid = 'album-mbid';
        $albumName = 'Album Name';
        $metadata = ['year' => 2023];
        $priority = 2;

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with(Task::TYPE_ADD_ALBUM, $albumMbid, null, $albumName)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createAddAlbumTask($albumMbid, $albumName, $metadata, $priority);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(Task::TYPE_ADD_ALBUM, $result->getType());
        $this->assertEquals($albumMbid, $result->getEntityMbid());
        $this->assertEquals($albumName, $result->getEntityName());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals($priority, $result->getPriority());
    }

    public function testCreateUpdateArtistTask(): void
    {
        $artistMbid = 'artist-mbid';
        $artistId = 123;
        $metadata = ['update' => 'metadata'];
        $priority = 1;

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with(Task::TYPE_UPDATE_ARTIST, $artistMbid, $artistId, null)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createUpdateArtistTask($artistMbid, $artistId, $metadata, $priority);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals(Task::TYPE_UPDATE_ARTIST, $result->getType());
        $this->assertEquals($artistMbid, $result->getEntityMbid());
        $this->assertEquals($artistId, $result->getEntityId());
        $this->assertEquals($metadata, $result->getMetadata());
        $this->assertEquals($priority, $result->getPriority());
    }

    public function testCreateTaskWithNullValues(): void
    {
        $type = 'test_task';

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with($type, null, null, null)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createTask($type);

        $this->assertInstanceOf(Task::class, $result);
        $this->assertEquals($type, $result->getType());
        $this->assertNull($result->getEntityMbid());
        $this->assertNull($result->getEntityId());
        $this->assertNull($result->getEntityName());
        $this->assertNull($result->getMetadata());
        $this->assertEquals(0, $result->getPriority());
    }

    public function testCreateTaskWithDefaultPriority(): void
    {
        $type = 'test_task';

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->with($type, null, null, null)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createTask($type);

        $this->assertEquals(0, $result->getPriority());
    }

    public function testCreateTaskGeneratesUniqueKey(): void
    {
        $type = 'test_task';

        $this->taskRepository
            ->expects($this->once())
            ->method('findActiveByTypeAndEntity')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->taskService->createTask($type);

        $this->assertNotEmpty($result->getUniqueKey());
        $this->assertIsString($result->getUniqueKey());
    }
}
