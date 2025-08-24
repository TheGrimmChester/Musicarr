<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Entity\Album;
use App\Entity\Artist;
use App\Service\ArtistAlbumGroupingService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class ArtistAlbumGroupingServiceTest extends TestCase
{
    private ArtistAlbumGroupingService $service;

    protected function setUp(): void
    {
        $this->service = new ArtistAlbumGroupingService();
    }

    public function testGroupAlbumsByReleaseGroupAndType(): void
    {
        $artist = $this->createArtist(1, 'Test Artist');

        $album1 = $this->createAlbum(1, 'Album 1', $artist, 'Album', 'group-1');
        $album2 = $this->createAlbum(2, 'Album 2', $artist, 'Album', 'group-1');
        $album3 = $this->createAlbum(3, 'Single 1', $artist, 'Single', 'group-2');
        $album4 = $this->createAlbum(4, 'EP 1', $artist, 'EP', null);
        $album5 = $this->createAlbum(5, 'Compilation 1', $artist, 'Compilation', null);

        $albums = [$album1, $album2, $album3, $album4, $album5];

        $result = $this->service->groupAlbumsByReleaseGroupAndType($albums);

        // Check albums by type
        $this->assertCount(2, $result['albumsByType']['Album']);
        $this->assertCount(1, $result['albumsByType']['Single']);
        $this->assertCount(1, $result['albumsByType']['EP']);
        $this->assertCount(1, $result['albumsByType']['Compilation']);

        // Check albums by release group
        $this->assertCount(2, $result['albumsByReleaseGroup']['group-1']['albums']);
        $this->assertCount(1, $result['albumsByReleaseGroup']['group-2']['albums']);
        $this->assertCount(2, $result['albumsByReleaseGroup']['no_group']['albums']);

        // Check no_group category
        $this->assertEquals('Albums without Release Group', $result['albumsByReleaseGroup']['no_group']['title']);
        $this->assertEquals('Unknown', $result['albumsByReleaseGroup']['no_group']['type']);
    }

    public function testGetAvailableStatuses(): void
    {
        $artist = $this->createArtist(1, 'Test Artist');

        $album1 = $this->createAlbum(1, 'Album 1', $artist, 'Album', 'group-1');
        $album1->setStatus('active');

        $album2 = $this->createAlbum(2, 'Album 2', $artist, 'Album', 'group-1');
        $album2->setStatus('inactive');

        $album3 = $this->createAlbum(3, 'Album 3', $artist, 'Album', 'group-1');
        $album3->setStatus('active');

        $albums = [$album1, $album2, $album3];

        $statuses = $this->service->getAvailableStatuses($albums);

        $this->assertCount(2, $statuses);
        $this->assertContains('active', $statuses);
        $this->assertContains('inactive', $statuses);
    }

    public function testGetAvailableStatusesWithNullValues(): void
    {
        $artist = $this->createArtist(1, 'Test Artist');

        $album1 = $this->createAlbum(1, 'Album 1', $artist, 'Album', 'group-1');
        $album1->setStatus('active');

        $album2 = $this->createAlbum(2, 'Album 2', $artist, 'Album', 'group-1');
        $album2->setStatus(null);

        $albums = [$album1, $album2];

        $statuses = $this->service->getAvailableStatuses($albums);

        $this->assertCount(1, $statuses);
        $this->assertContains('active', $statuses);
        $this->assertNotContains(null, $statuses);
    }

    public function testGroupAlbumsWithEmptyArray(): void
    {
        $result = $this->service->groupAlbumsByReleaseGroupAndType([]);

        $this->assertEmpty($result['albumsByType']['Album']);
        $this->assertEmpty($result['albumsByType']['Single']);
        $this->assertEmpty($result['albumsByType']['EP']);
        $this->assertEmpty($result['albumsByType']['Compilation']);
        $this->assertEmpty($result['albumsByReleaseGroup']);
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

    private function createAlbum(int $id, string $title, Artist $artist, string $type, ?string $releaseGroupMbid): Album
    {
        $album = new Album();
        $album->setTitle($title);
        $album->setArtist($artist);
        $album->setAlbumType($type);
        $album->setReleaseGroupMbid($releaseGroupMbid);

        // Use reflection to set the ID
        $reflection = new ReflectionClass($album);
        $property = $reflection->getProperty('id');
        $property->setAccessible(true);
        $property->setValue($album, $id);

        return $album;
    }
}
