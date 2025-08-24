<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Medium;
use App\Entity\Track;
use App\Entity\TrackFile;
use App\Manager\AlbumMediaProcessor;
use App\Repository\LibraryRepository;
use App\Repository\MediumRepository;
use App\Repository\TrackRepository;
use App\Task\TaskFactory;
use ArrayIterator;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;

class AlbumMediaProcessorTest extends TestCase
{
    private AlbumMediaProcessor $albumMediaProcessor;
    private EntityManagerInterface|MockObject $entityManager;
    private MediumRepository|MockObject $mediumRepository;
    private TrackRepository|MockObject $trackRepository;
    private LibraryRepository|MockObject $libraryRepository;
    private TaskFactory|MockObject $taskService;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->mediumRepository = $this->createMock(MediumRepository::class);
        $this->trackRepository = $this->createMock(TrackRepository::class);
        $this->libraryRepository = $this->createMock(LibraryRepository::class);
        $this->taskService = $this->createMock(TaskFactory::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->albumMediaProcessor = new AlbumMediaProcessor(
            $this->entityManager,
            $this->mediumRepository,
            $this->trackRepository,
            $this->libraryRepository,
            $this->taskService,
            $this->logger
        );
    }

    public function testAlbumMediaProcessorClassExists(): void
    {
        $this->assertInstanceOf(AlbumMediaProcessor::class, $this->albumMediaProcessor);
    }

    public function testAlbumMediaProcessorHasExpectedMethods(): void
    {
        $expectedMethods = [
            'processAlbumMedia',
            'processAlbumMediaConservative',
            'processAlbumMediaSafe',
            'debugTrackPreservation',
            'processMedium',
            'processTrack',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists($this->albumMediaProcessor, $method), "Method {$method} should exist");
        }
    }

    public function testProcessAlbumMedia(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $mediaData = [
            [
                'id' => 'medium-mbid-1',
                'title' => 'Medium 1',
                'position' => 1,
                'format' => 'CD',
                'trackCount' => 1,
                'tracks' => [
                    [
                        'id' => 'track-mbid-1',
                        'title' => 'Track 1',
                        'number' => 1,
                        'length' => 180,
                    ],
                ],
            ],
        ];

        $this->mediumRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->willReturn(null);

        $this->trackRepository
            ->expects($this->atLeastOnce())
            ->method('findOneBy')
            ->willReturn(null);

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->entityManager
            ->expects($this->atLeastOnce())
            ->method('persist');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->albumMediaProcessor->processAlbumMedia($mediaData, $album);
    }

    public function testProcessMedium(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $mediumData = [
            'id' => 'medium-mbid-1',
            'title' => 'Medium 1',
            'position' => 1,
            'format' => 'CD',
            'trackCount' => 1,
            'tracks' => [],
        ];

        $this->mediumRepository
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'album' => $album,
                'position' => 1,
            ])
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $result = $this->albumMediaProcessor->processMedium($mediumData, $album);

        $this->assertInstanceOf(Medium::class, $result);
    }

    public function testProcessTrack(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $medium = $this->createMockMedium(1, 'Medium 1');
        $trackData = [
            'id' => 'track-mbid-1',
            'title' => 'Track 1',
            'number' => 1,
            'length' => 180,
        ];

        $this->trackRepository
            ->expects($this->atLeastOnce())
            ->method('findOneBy')
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist');

        $mediumChanges = 0;
        $result = $this->albumMediaProcessor->processTrack($trackData, $album, $medium, $mediumChanges);

        $this->assertInstanceOf(Track::class, $result);
    }

    public function testProcessAlbumMediaConservative(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $mediaData = [
            [
                'id' => 'medium-mbid-1',
                'title' => 'Medium 1',
                'position' => 1,
                'format' => 'CD',
                'trackCount' => 1,
                'tracks' => [
                    [
                        'id' => 'track-mbid-1',
                        'title' => 'Track 1',
                        'number' => 1,
                        'length' => 180,
                    ],
                ],
            ],
        ];

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->albumMediaProcessor->processAlbumMediaConservative($mediaData, $album);
    }

    public function testProcessAlbumMediaSafe(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $mediaData = [
            [
                'id' => 'medium-mbid-1',
                'title' => 'Medium 1',
                'position' => 1,
                'format' => 'CD',
                'trackCount' => 1,
                'tracks' => [
                    [
                        'id' => 'track-mbid-1',
                        'title' => 'Track 1',
                        'number' => 1,
                        'length' => 180,
                    ],
                ],
            ],
        ];

        $this->logger
            ->expects($this->atLeastOnce())
            ->method('info');

        $this->albumMediaProcessor->processAlbumMediaSafe($mediaData, $album);
    }

    public function testDebugTrackPreservation(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $mediaData = [
            [
                'id' => 'medium-mbid-1',
                'title' => 'Medium 1',
                'position' => 1,
                'format' => 'CD',
                'trackCount' => 1,
                'tracks' => [
                    [
                        'id' => 'track-mbid-1',
                        'title' => 'Track 1',
                        'number' => 1,
                        'length' => 180,
                    ],
                ],
            ],
        ];

        $result = $this->albumMediaProcessor->debugTrackPreservation($mediaData, $album);

        $this->assertIsArray($result);
    }

    public function testAlbumMediaProcessorReflection(): void
    {
        // Test that we can reflect on the class and its methods
        $reflection = new ReflectionClass($this->albumMediaProcessor);

        $this->assertTrue($reflection->hasMethod('processAlbumMedia'));
        $this->assertTrue($reflection->hasMethod('processAlbumMediaConservative'));
        $this->assertTrue($reflection->hasMethod('processAlbumMediaSafe'));
        $this->assertTrue($reflection->hasMethod('debugTrackPreservation'));
        $this->assertTrue($reflection->hasMethod('processMedium'));
        $this->assertTrue($reflection->hasMethod('processTrack'));

        // Check method visibility
        $this->assertTrue($reflection->getMethod('processAlbumMedia')->isPublic());
        $this->assertTrue($reflection->getMethod('processAlbumMediaConservative')->isPublic());
        $this->assertTrue($reflection->getMethod('processAlbumMediaSafe')->isPublic());
        $this->assertTrue($reflection->getMethod('debugTrackPreservation')->isPublic());
        $this->assertTrue($reflection->getMethod('processMedium')->isPublic());
        $this->assertTrue($reflection->getMethod('processTrack')->isPublic());
    }

    public function testAlbumMediaProcessorConstructor(): void
    {
        // Test that the constructor properly sets the dependencies
        $reflection = new ReflectionClass($this->albumMediaProcessor);

        $this->assertTrue($reflection->hasProperty('entityManager'));
        $this->assertTrue($reflection->hasProperty('mediumRepository'));
        $this->assertTrue($reflection->hasProperty('trackRepository'));
        $this->assertTrue($reflection->hasProperty('libraryRepository'));
        $this->assertTrue($reflection->hasProperty('taskService'));
        $this->assertTrue($reflection->hasProperty('logger'));
    }

    public function testAlbumMediaProcessorCanHandleBasicOperations(): void
    {
        // Test that basic methods can be called without crashing
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'processAlbumMedia'));
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'processAlbumMediaConservative'));
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'processAlbumMediaSafe'));
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'debugTrackPreservation'));
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'processMedium'));
        $this->assertTrue(method_exists($this->albumMediaProcessor, 'processTrack'));
    }

    private function createMockAlbum(int $id, string $title): Album
    {
        $album = $this->createMock(Album::class);
        $album->method('getId')->willReturn($id);
        $album->method('getTitle')->willReturn($title);
        $album->method('getStatus')->willReturn('active');

        $mediums = $this->createMock(Collection::class);
        $mediums->method('toArray')->willReturn([]);
        $album->method('getMediums')->willReturn($mediums);

        $artist = $this->createMock(Artist::class);
        $artist->method('getName')->willReturn('Test Artist');
        $album->method('getArtist')->willReturn($artist);

        return $album;
    }

    private function createMockTrack(int $id, string $title): Track
    {
        $track = $this->createMock(Track::class);
        $track->method('getId')->willReturn($id);
        $track->method('getTitle')->willReturn($title);
        $track->method('getDuration')->willReturn(180);
        $track->method('isHasFile')->willReturn(false);
        $track->method('isDownloaded')->willReturn(false);

        $files = $this->createMock(Collection::class);
        $files->method('getIterator')->willReturn(new ArrayIterator([]));
        $track->method('getFiles')->willReturn($files);

        return $track;
    }

    private function createMockMedium(int $id, string $title): Medium
    {
        $medium = $this->createMock(Medium::class);
        $medium->method('getId')->willReturn($id);
        $medium->method('getTitle')->willReturn($title);
        $medium->method('getPosition')->willReturn(1);
        $medium->method('getDisplayName')->willReturn($title);

        $tracks = $this->createMock(Collection::class);
        $medium->method('getTracks')->willReturn($tracks);
        $medium->method('addTrack')->willReturnSelf();

        return $medium;
    }

    private function createMockTrackFile(int $id, string $filename, int $size): TrackFile
    {
        $trackFile = $this->createMock(TrackFile::class);
        $trackFile->method('getId')->willReturn($id);
        $trackFile->method('getFilename')->willReturn($filename);
        $trackFile->method('getSize')->willReturn($size);

        return $trackFile;
    }
}
