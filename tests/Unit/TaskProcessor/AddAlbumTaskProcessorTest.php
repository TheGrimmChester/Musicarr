<?php

declare(strict_types=1);

namespace App\Tests\Unit\TaskProcessor;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Task\Processor\AddAlbumTaskProcessor;
use App\Task\Processor\TaskProcessorResult;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class AddAlbumTaskProcessorTest extends TestCase
{
    private AddAlbumTaskProcessor $processor;
    private EntityManagerInterface $entityManager;
    private MusicLibraryManager $musicLibraryManager;
    private AlbumRepository $albumRepository;
    private ArtistRepository $artistRepository;
    private TaskFactory $taskService;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->musicLibraryManager = $this->createMock(MusicLibraryManager::class);
        $this->albumRepository = $this->createMock(AlbumRepository::class);
        $this->artistRepository = $this->createMock(ArtistRepository::class);
        $this->taskService = $this->createMock(TaskFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new AddAlbumTaskProcessor(
            $this->musicLibraryManager,
            $this->albumRepository,
            $this->artistRepository,
            $this->entityManager,
            $this->taskService,
            $this->logger
        );
    }

    public function testProcessAddAlbumWithMbid(): void
    {
        $task = new Task();
        $task->setEntityMbid('test-mbid-123');
        $task->setEntityName('Test Album');
        $task->setMetadata([
            'artist_id' => 1,
            'artist_name' => 'Test Artist',
            'release_group_id' => 'group-123',
        ]);

        $artist = $this->createArtist(1, 'Test Artist');
        $album = $this->createAlbum(1, 'Test Album', $artist);

        $this->artistRepository->method('find')
            ->with(1)
            ->willReturn($artist);

        $this->albumRepository->method('findOneBy')
            ->with(['releaseMbid' => 'test-mbid-123'])
            ->willReturn(null);

        $this->musicLibraryManager->method('addAlbumWithMbid')
            ->with('Test Album', 'test-mbid-123', 'group-123', 1)
            ->willReturn($album);

        $result = $this->processor->process($task);

        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('Successfully added album', $result->getMessage());
    }

    public function testProcessWithMissingRequiredData(): void
    {
        $task = new Task();
        $task->setEntityMbid(null); // Missing MBID
        $task->setEntityName('Test Album');
        $task->setMetadata([
            'artist_id' => 1,
        ]);

        $result = $this->processor->process($task);

        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Missing required data', $result->getErrorMessage());
    }

    public function testProcessWithArtistNotFound(): void
    {
        $task = new Task();
        $task->setEntityMbid('test-mbid-123');
        $task->setEntityName('Test Album');
        $task->setMetadata([
            'artist_id' => 999, // Non-existent artist
            'artist_name' => 'Test Artist',
        ]);

        $this->artistRepository->method('find')
            ->with(999)
            ->willReturn(null);

        $result = $this->processor->process($task);

        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Artist with ID 999 not found', $result->getErrorMessage());
    }

    public function testProcessWithExistingAlbum(): void
    {
        $task = new Task();
        $task->setEntityMbid('test-mbid-123');
        $task->setEntityName('Test Album');
        $task->setMetadata([
            'artist_id' => 1,
            'artist_name' => 'Test Artist',
        ]);

        $artist = $this->createArtist(1, 'Test Artist');
        $existingAlbum = $this->createAlbum(1, 'Test Album', $artist);

        $this->artistRepository->method('find')
            ->with(1)
            ->willReturn($artist);

        $this->albumRepository->method('findOneBy')
            ->with(['releaseMbid' => 'test-mbid-123'])
            ->willReturn($existingAlbum);

        $result = $this->processor->process($task);

        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('already exists', $result->getMessage());
        $this->assertEquals('already_exists', $result->getMetadata()['status']);
    }

    public function testProcessWithFailedAlbumCreation(): void
    {
        $task = new Task();
        $task->setEntityMbid('test-mbid-123');
        $task->setEntityName('Test Album');
        $task->setMetadata([
            'artist_id' => 1,
            'artist_name' => 'Test Artist',
        ]);

        $artist = $this->createArtist(1, 'Test Artist');

        $this->artistRepository->method('find')
            ->with(1)
            ->willReturn($artist);

        $this->albumRepository->method('findOneBy')
            ->with(['releaseMbid' => 'test-mbid-123'])
            ->willReturn(null);

        $this->musicLibraryManager->method('addAlbumWithMbid')
            ->with('Test Album', 'test-mbid-123', null, 1)
            ->willReturn(null);

        $result = $this->processor->process($task);

        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('Failed to add album', $result->getErrorMessage());
    }

    public function testGetSupportedTaskTypes(): void
    {
        $result = $this->processor->getSupportedTaskTypes();
        $this->assertIsArray($result);
        $this->assertContains('add_album', $result);
    }

    public function testSupports(): void
    {
        $task = new Task();
        $task->setType('add_album');

        $result = $this->processor->supports($task);
        $this->assertIsBool($result);
    }

    private function createArtist(int $id, string $name): Artist
    {
        $artist = new Artist();
        $artist->setName($name);

        // Use reflection to set the ID
        $reflection = new ReflectionClass($artist);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($artist, $id);

        return $artist;
    }

    private function createAlbum(int $id, string $title, Artist $artist): Album
    {
        $album = new Album();
        $album->setTitle($title);
        $album->setArtist($artist);

        // Use reflection to set the ID
        $reflection = new ReflectionClass($album);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($album, $id);

        return $album;
    }
}
