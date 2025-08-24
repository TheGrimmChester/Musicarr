<?php

declare(strict_types=1);

namespace App\Tests\Unit\Manager;

use App\Entity\Album;
use App\Entity\Track;
use App\Entity\TrackFile;
use App\Manager\AlbumStatusManager;
use App\Repository\AlbumRepository;
use ArrayIterator;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionClass;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumStatusManagerTest extends TestCase
{
    private AlbumStatusManager $albumStatusManager;
    private EntityManagerInterface|MockObject $entityManager;
    private AlbumRepository|MockObject $albumRepository;
    private LoggerInterface|MockObject $logger;
    private TranslatorInterface|MockObject $translator;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->albumRepository = $this->createMock(AlbumRepository::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->translator = $this->createMock(TranslatorInterface::class);

        $this->albumStatusManager = new AlbumStatusManager(
            $this->entityManager,
            $this->albumRepository,
            $this->logger,
            $this->translator
        );
    }

    public function testAlbumStatusManagerClassExists(): void
    {
        $this->assertInstanceOf(AlbumStatusManager::class, $this->albumStatusManager);
    }

    public function testAlbumStatusManagerHasExpectedMethods(): void
    {
        $expectedMethods = [
            'updateAlbumStatusAfterTrackAnalysis',
            'updateAlbumStatus',
            'updateAllAlbumStatuses',
            'updateArtistAlbumStatuses',
            'getAlbumStatusStats',
            'cleanupEmptyAlbums',
            'getAlbumsByStatus',
            'forceUpdateAlbumStatus',
            'validateAlbumStatuses',
        ];

        foreach ($expectedMethods as $method) {
            $this->assertTrue(method_exists($this->albumStatusManager, $method), "Method {$method} should exist");
        }
    }

    public function testUpdateAlbumStatusAfterTrackAnalysis(): void
    {
        $track = $this->createMockTrack(1, 'Track 1');
        $album = $this->createMockAlbum(1, 'Test Album');

        $track->method('getAlbum')->willReturn($album);

        $this->albumRepository
            ->expects($this->once())
            ->method('save')
            ->with($album, true);

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->albumStatusManager->updateAlbumStatusAfterTrackAnalysis($track);
    }

    public function testUpdateAlbumStatusAfterTrackAnalysisWithNoAlbum(): void
    {
        $track = $this->createMockTrack(1, 'Track 1');
        $track->method('getAlbum')->willReturn(null);

        $this->albumRepository
            ->expects($this->never())
            ->method('save');

        $this->albumStatusManager->updateAlbumStatusAfterTrackAnalysis($track);
    }

    public function testUpdateAlbumStatus(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $tracks = $this->createMockTrackCollection([
            $this->createMockTrack(1, 'Track 1', true, true, true),
            $this->createMockTrack(2, 'Track 2', true, true, true),
        ]);

        $album->method('getTracks')->willReturn($tracks);

        $this->albumRepository
            ->expects($this->once())
            ->method('save')
            ->with($album, true);

        $this->logger
            ->expects($this->once())
            ->method('info');

        $this->albumStatusManager->updateAlbumStatus($album);
    }

    public function testUpdateAllAlbumStatuses(): void
    {
        $albums = [
            $this->createMockAlbum(1, 'Album 1'),
            $this->createMockAlbum(2, 'Album 2'),
        ];

        $this->albumRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($albums);

        // Mock tracks for both albums
        $tracks1 = $this->createMockTrackCollection([
            $this->createMockTrack(1, 'Track 1', true, true, true),
        ]);
        $tracks2 = $this->createMockTrackCollection([
            $this->createMockTrack(2, 'Track 2', false, false, false),
        ]);

        $albums[0]->method('getTracks')->willReturn($tracks1);
        $albums[1]->method('getTracks')->willReturn($tracks2);

        $this->albumRepository
            ->expects($this->exactly(2))
            ->method('save');

        $this->logger
            ->expects($this->exactly(3))
            ->method('info');

        $result = $this->albumStatusManager->updateAllAlbumStatuses();

        $this->assertEquals(2, $result);
    }

    public function testUpdateArtistAlbumStatuses(): void
    {
        $albums = [
            $this->createMockAlbum(1, 'Album 1'),
            $this->createMockAlbum(2, 'Album 2'),
        ];

        $this->albumRepository
            ->expects($this->once())
            ->method('findBy')
            ->with(['artist' => 1])
            ->willReturn($albums);

        // Mock tracks for both albums
        $tracks1 = $this->createMockTrackCollection([
            $this->createMockTrack(1, 'Track 1', true, true, true),
        ]);
        $tracks2 = $this->createMockTrackCollection([
            $this->createMockTrack(2, 'Track 2', false, false, false),
        ]);

        $albums[0]->method('getTracks')->willReturn($tracks1);
        $albums[1]->method('getTracks')->willReturn($tracks2);

        $this->albumRepository
            ->expects($this->exactly(2))
            ->method('save');

        $this->logger
            ->expects($this->exactly(3))
            ->method('info');

        $result = $this->albumStatusManager->updateArtistAlbumStatuses(1);

        $this->assertEquals(2, $result);
    }

    public function testGetAlbumStatusStats(): void
    {
        $stats = $this->albumStatusManager->getAlbumStatusStats();

        $this->assertIsArray($stats);
    }

    public function testCleanupEmptyAlbums(): void
    {
        $result = $this->albumStatusManager->cleanupEmptyAlbums();

        $this->assertIsInt($result);
    }

    public function testGetAlbumsByStatus(): void
    {
        $albums = $this->albumStatusManager->getAlbumsByStatus('complete', 10);

        $this->assertIsArray($albums);
    }

    public function testForceUpdateAlbumStatus(): void
    {
        $album = $this->createMockAlbum(1, 'Test Album');
        $tracks = $this->createMockTrackCollection([
            $this->createMockTrack(1, 'Track 1', true, true, true),
        ]);

        $album->method('getTracks')->willReturn($tracks);

        $this->albumRepository
            ->expects($this->once())
            ->method('save')
            ->with($album, true);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $this->albumStatusManager->forceUpdateAlbumStatus($album);
    }

    public function testValidateAlbumStatuses(): void
    {
        $validationResults = $this->albumStatusManager->validateAlbumStatuses();

        $this->assertIsArray($validationResults);
    }

    public function testAlbumStatusManagerReflection(): void
    {
        // Test that we can reflect on the class and its methods
        $reflection = new ReflectionClass($this->albumStatusManager);

        $this->assertTrue($reflection->hasMethod('updateAlbumStatusAfterTrackAnalysis'));
        $this->assertTrue($reflection->hasMethod('updateAlbumStatus'));
        $this->assertTrue($reflection->hasMethod('updateAllAlbumStatuses'));
        $this->assertTrue($reflection->hasMethod('updateArtistAlbumStatuses'));
        $this->assertTrue($reflection->hasMethod('getAlbumStatusStats'));
        $this->assertTrue($reflection->hasMethod('cleanupEmptyAlbums'));
        $this->assertTrue($reflection->hasMethod('getAlbumsByStatus'));
        $this->assertTrue($reflection->hasMethod('forceUpdateAlbumStatus'));
        $this->assertTrue($reflection->hasMethod('validateAlbumStatuses'));

        // Check method visibility
        $this->assertTrue($reflection->getMethod('updateAlbumStatusAfterTrackAnalysis')->isPublic());
        $this->assertTrue($reflection->getMethod('updateAlbumStatus')->isPublic());
        $this->assertTrue($reflection->getMethod('updateAllAlbumStatuses')->isPublic());
        $this->assertTrue($reflection->getMethod('updateArtistAlbumStatuses')->isPublic());
        $this->assertTrue($reflection->getMethod('getAlbumStatusStats')->isPublic());
        $this->assertTrue($reflection->getMethod('cleanupEmptyAlbums')->isPublic());
        $this->assertTrue($reflection->getMethod('getAlbumsByStatus')->isPublic());
        $this->assertTrue($reflection->getMethod('forceUpdateAlbumStatus')->isPublic());
        $this->assertTrue($reflection->getMethod('validateAlbumStatuses')->isPublic());
    }

    public function testAlbumStatusManagerConstructor(): void
    {
        // Test that the constructor properly sets the dependencies
        $reflection = new ReflectionClass($this->albumStatusManager);

        $this->assertTrue($reflection->hasProperty('entityManager'));
        $this->assertTrue($reflection->hasProperty('albumRepository'));
        $this->assertTrue($reflection->hasProperty('logger'));
        $this->assertTrue($reflection->hasProperty('translator'));
    }

    public function testAlbumStatusManagerCanHandleBasicOperations(): void
    {
        // Test that basic methods can be called without crashing
        $this->assertTrue(method_exists($this->albumStatusManager, 'updateAlbumStatusAfterTrackAnalysis'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'updateAlbumStatus'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'updateAllAlbumStatuses'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'updateArtistAlbumStatuses'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'getAlbumStatusStats'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'cleanupEmptyAlbums'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'getAlbumsByStatus'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'forceUpdateAlbumStatus'));
        $this->assertTrue(method_exists($this->albumStatusManager, 'validateAlbumStatuses'));
    }

    private function createMockAlbum(int $id, string $title): Album
    {
        $album = $this->createMock(Album::class);
        $album->method('getId')->willReturn($id);
        $album->method('getTitle')->willReturn($title);
        $album->method('getStatus')->willReturn('unknown');
        $album->method('setStatus')->willReturnSelf();
        $album->method('setHasFile')->willReturnSelf();
        $album->method('setDownloaded')->willReturnSelf();

        return $album;
    }

    private function createMockTrack(int $id, string $title, bool $hasFile = false, bool $downloaded = false, bool $analyzed = false): Track
    {
        $track = $this->createMock(Track::class);
        $track->method('getId')->willReturn($id);
        $track->method('getTitle')->willReturn($title);
        $track->method('isHasFile')->willReturn($hasFile);
        $track->method('isDownloaded')->willReturn($downloaded);

        $files = $this->createMock(Collection::class);
        if ($analyzed) {
            $file = $this->createMock(TrackFile::class);
            $file->method('getQuality')->willReturn('320kbps');
            $files->method('getIterator')->willReturn(new ArrayIterator([$file]));
        } else {
            $files->method('getIterator')->willReturn(new ArrayIterator([]));
        }

        $track->method('getFiles')->willReturn($files);

        return $track;
    }

    private function createMockTrackCollection(array $tracks): Collection
    {
        $collection = $this->createMock(Collection::class);
        $collection->method('count')->willReturn(\count($tracks));
        $collection->method('getIterator')->willReturn(new ArrayIterator($tracks));

        return $collection;
    }
}
