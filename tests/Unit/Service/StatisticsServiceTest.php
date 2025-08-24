<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Album;
use App\Entity\AlbumStatistic;
use App\Entity\Artist;
use App\Entity\ArtistStatistic;
use App\Entity\Library;
use App\Entity\LibraryStatistic;
use App\Repository\AlbumStatisticRepository;
use App\Repository\ArtistStatisticRepository;
use App\Repository\LibraryStatisticRepository;
use App\Statistic\StatisticsService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StatisticsServiceTest extends TestCase
{
    private StatisticsService $statisticsService;
    private LibraryStatisticRepository|MockObject $libraryStatisticRepository;
    private ArtistStatisticRepository|MockObject $artistStatisticRepository;
    private AlbumStatisticRepository|MockObject $albumStatisticRepository;

    protected function setUp(): void
    {
        $this->libraryStatisticRepository = $this->createMock(LibraryStatisticRepository::class);
        $this->artistStatisticRepository = $this->createMock(ArtistStatisticRepository::class);
        $this->albumStatisticRepository = $this->createMock(AlbumStatisticRepository::class);

        $this->statisticsService = new StatisticsService(
            $this->libraryStatisticRepository,
            $this->artistStatisticRepository,
            $this->albumStatisticRepository
        );
    }

    public function testGetLibraryStatisticsWithExistingStats(): void
    {
        $library = $this->createMock(Library::class);
        $stats = $this->createMock(LibraryStatistic::class);
        $expectedArray = ['total_albums' => 10, 'total_tracks' => 100];

        $stats->method('toArray')->willReturn($expectedArray);

        $this->libraryStatisticRepository
            ->expects($this->once())
            ->method('findByLibrary')
            ->with($library)
            ->willReturn($stats);

        $result = $this->statisticsService->getLibraryStatistics($library);

        $this->assertEquals($expectedArray, $result);
    }

    public function testGetLibraryStatisticsWithNoStats(): void
    {
        $library = $this->createMock(Library::class);

        $this->libraryStatisticRepository
            ->expects($this->once())
            ->method('findByLibrary')
            ->with($library)
            ->willReturn(null);

        $result = $this->statisticsService->getLibraryStatistics($library);

        $this->assertNull($result);
    }

    public function testGetAllLibraryStatistics(): void
    {
        $stats1 = $this->createMock(LibraryStatistic::class);
        $stats1->method('toArray')->willReturn(['library_id' => 1, 'total_albums' => 10]);

        $stats2 = $this->createMock(LibraryStatistic::class);
        $stats2->method('toArray')->willReturn(['library_id' => 2, 'total_albums' => 20]);

        $this->libraryStatisticRepository
            ->expects($this->once())
            ->method('findAllWithLibrary')
            ->willReturn([$stats1, $stats2]);

        $result = $this->statisticsService->getAllLibraryStatistics();

        $expected = [
            ['library_id' => 1, 'total_albums' => 10],
            ['library_id' => 2, 'total_albums' => 20],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetArtistStatisticsWithExistingStats(): void
    {
        $artist = $this->createMock(Artist::class);
        $stats = $this->createMock(ArtistStatistic::class);
        $expectedArray = ['total_albums' => 5, 'total_tracks' => 50];

        $stats->method('toArray')->willReturn($expectedArray);

        $this->artistStatisticRepository
            ->expects($this->once())
            ->method('findByArtist')
            ->with($artist)
            ->willReturn($stats);

        $result = $this->statisticsService->getArtistStatistics($artist);

        $this->assertEquals($expectedArray, $result);
    }

    public function testGetArtistStatisticsWithNoStats(): void
    {
        $artist = $this->createMock(Artist::class);

        $this->artistStatisticRepository
            ->expects($this->once())
            ->method('findByArtist')
            ->with($artist)
            ->willReturn(null);

        $result = $this->statisticsService->getArtistStatistics($artist);

        $this->assertNull($result);
    }

    public function testGetArtistStatisticsByLibrary(): void
    {
        $stats1 = $this->createMock(ArtistStatistic::class);
        $stats1->method('toArray')->willReturn(['artist_id' => 1, 'total_albums' => 3]);

        $stats2 = $this->createMock(ArtistStatistic::class);
        $stats2->method('toArray')->willReturn(['artist_id' => 2, 'total_albums' => 7]);

        $this->artistStatisticRepository
            ->expects($this->once())
            ->method('findByLibraryId')
            ->with(1)
            ->willReturn([$stats1, $stats2]);

        $result = $this->statisticsService->getArtistStatisticsByLibrary(1);

        $expected = [
            ['artist_id' => 1, 'total_albums' => 3],
            ['artist_id' => 2, 'total_albums' => 7],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetTopArtistsByAlbums(): void
    {
        $stats1 = $this->createMock(ArtistStatistic::class);
        $stats1->method('toArray')->willReturn(['artist_id' => 1, 'total_albums' => 10]);

        $stats2 = $this->createMock(ArtistStatistic::class);
        $stats2->method('toArray')->willReturn(['artist_id' => 2, 'total_albums' => 8]);

        $this->artistStatisticRepository
            ->expects($this->once())
            ->method('findTopArtistsByAlbums')
            ->with(5)
            ->willReturn([$stats1, $stats2]);

        $result = $this->statisticsService->getTopArtistsByAlbums(5);

        $expected = [
            ['artist_id' => 1, 'total_albums' => 10],
            ['artist_id' => 2, 'total_albums' => 8],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetTopArtistsByTracks(): void
    {
        $stats1 = $this->createMock(ArtistStatistic::class);
        $stats1->method('toArray')->willReturn(['artist_id' => 1, 'total_tracks' => 100]);

        $stats2 = $this->createMock(ArtistStatistic::class);
        $stats2->method('toArray')->willReturn(['artist_id' => 2, 'total_tracks' => 80]);

        $this->artistStatisticRepository
            ->expects($this->once())
            ->method('findTopArtistsByTracks')
            ->with(10)
            ->willReturn([$stats1, $stats2]);

        $result = $this->statisticsService->getTopArtistsByTracks(10);

        $expected = [
            ['artist_id' => 1, 'total_tracks' => 100],
            ['artist_id' => 2, 'total_tracks' => 80],
        ];
        $this->assertEquals($expected, $result);
    }

    public function testGetAlbumStatisticsWithExistingStats(): void
    {
        $album = $this->createMock(Album::class);
        $stats = $this->createMock(AlbumStatistic::class);
        $expectedArray = ['total_tracks' => 12, 'downloaded_tracks' => 8];

        $stats->method('toArray')->willReturn($expectedArray);

        $this->albumStatisticRepository
            ->expects($this->once())
            ->method('findByAlbum')
            ->with($album)
            ->willReturn($stats);

        $result = $this->statisticsService->getAlbumStatistics($album);

        $this->assertEquals($expectedArray, $result);
    }

    public function testGetAlbumStatisticsWithNoStats(): void
    {
        $album = $this->createMock(Album::class);

        $this->albumStatisticRepository
            ->expects($this->once())
            ->method('findByAlbum')
            ->with($album)
            ->willReturn(null);

        $result = $this->statisticsService->getAlbumStatistics($album);

        $this->assertNull($result);
    }

    public function testGetAlbumStatisticsByArtist(): void
    {
        $stats1 = $this->createMock(AlbumStatistic::class);
        $stats1->method('toArray')->willReturn(['album_id' => 1, 'total_tracks' => 12]);

        $stats2 = $this->createMock(AlbumStatistic::class);
        $stats2->method('toArray')->willReturn(['album_id' => 2, 'total_tracks' => 10]);

        $this->albumStatisticRepository
            ->expects($this->once())
            ->method('findByArtistId')
            ->with(1)
            ->willReturn([$stats1, $stats2]);

        $result = $this->statisticsService->getAlbumStatisticsByArtist(1);

        $expected = [
            ['album_id' => 1, 'total_tracks' => 12],
            ['album_id' => 2, 'total_tracks' => 10],
        ];
        $this->assertEquals($expected, $result);
    }
}
