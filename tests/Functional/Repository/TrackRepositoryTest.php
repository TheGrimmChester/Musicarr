<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Library;
use App\Entity\Track;
use App\Repository\TrackRepository;

class TrackRepositoryTest extends AbstractRepositoryTestCase
{
    private TrackRepository $trackRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->trackRepository = $this->entityManager->getRepository(Track::class);
    }

    public function testFindAllIterable(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);

        $track1 = $this->createTestTrack('Track 1', $album);
        $track2 = $this->createTestTrack('Track 2', $album);
        $track3 = $this->createTestTrack('Track 3', $album);

        $iterable = $this->trackRepository->findAllIterable();
        $tracks = iterator_to_array($iterable);

        $this->assertCount(3, $tracks);
        $this->assertContains($track1, $tracks);
        $this->assertContains($track2, $tracks);
        $this->assertContains($track3, $tracks);
    }

    public function testFindByArtistAlbumAndTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album',
            'Test Track'
        );

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
        $this->assertEquals('Test Track', $foundTrack->getTitle());
    }

    public function testFindByArtistAlbumAndTitleWithCleanAlbumTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album EP', $artist);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album EP',
            'Test Track'
        );

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
    }

    public function testFindByArtistAlbumAndTitleWithApostropheInTrackTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack("Artist's Song", $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album',
            "Artist's Song"
        );

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
    }

    public function testFindByArtistAlbumAndTitleReturnsNullWhenNotFound(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album',
            'Non-existent Track'
        );

        $this->assertNull($foundTrack);
    }

    public function testFindByArtistAlbumAndTitleWithDifferentArtist(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist1 = $this->createTestArtist('Artist 1', $library);
        $artist2 = $this->createTestArtist('Artist 2', $library);
        $album = $this->createTestAlbum('Test Album', $artist1);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Artist 2',
            'Test Album',
            'Test Track'
        );

        $this->assertNull($foundTrack);
    }

    public function testFindByArtistAlbumAndTitleWithDifferentAlbum(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album1 = $this->createTestAlbum('Album 1', $artist);
        $album2 = $this->createTestAlbum('Album 2', $artist);
        $track = $this->createTestTrack('Test Track', $album1);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Album 2',
            'Test Track'
        );

        $this->assertNull($foundTrack);
    }

    public function testFindByAlbumAndTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByAlbumAndTitle($album->getId(), 'Test Track');

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
        $this->assertEquals('Test Track', $foundTrack->getTitle());
    }

    public function testFindByAlbumAndTitleWithCleanTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByAlbumAndTitle($album->getId(), 'Test Track');

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
    }

    public function testFindByAlbumAndTitleReturnsNullWhenNotFound(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);

        $foundTrack = $this->trackRepository->findByAlbumAndTitle($album->getId(), 'Non-existent Track');

        $this->assertNull($foundTrack);
    }

    public function testFindByAlbumAndTitleWithDifferentAlbumId(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack('Test Track', $album);

        $foundTrack = $this->trackRepository->findByAlbumAndTitle(99999, 'Test Track');

        $this->assertNull($foundTrack);
    }

    public function testFindByAlbumAndTitleWithMultipleTracks(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track1 = $this->createTestTrack('Track 1', $album);
        $track2 = $this->createTestTrack('Track 2', $album);

        $foundTrack = $this->trackRepository->findByAlbumAndTitle($album->getId(), 'Track 1');

        $this->assertNotNull($foundTrack);
        $this->assertEquals('Track 1', $foundTrack->getTitle());
    }

    public function testFindByArtistAlbumAndTitleWithSpecialCharacters(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack("Track with 'quotes' and \"double quotes\"", $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album',
            "Track with 'quotes' and \"double quotes\""
        );

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
    }

    public function testFindByArtistAlbumAndTitleWithEmptyTrackTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('Test Artist', $library);
        $album = $this->createTestAlbum('Test Album', $artist);
        $track = $this->createTestTrack('', $album);

        $foundTrack = $this->trackRepository->findByArtistAlbumAndTitle(
            'Test Artist',
            'Test Album',
            ''
        );

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
    }

    protected function createTestLibrary(string $name): Library
    {
        $library = new Library();
        $library->setName($name);
        $library->setPath('/test/path');
        $this->persistEntity($library);

        return $library;
    }

    protected function createTestArtist(string $name, ?Library $library = null): Artist
    {
        $artist = new Artist();
        $artist->setName($name);
        // Note: Library relationship is not currently implemented in Artist entity
        $this->persistEntity($artist);

        return $artist;
    }

    protected function createTestAlbum(string $title, Artist $artist): Album
    {
        $album = new Album();
        $album->setTitle($title);
        $album->setArtist($artist);
        $album->setReleaseMbid('test-release-mbid-' . uniqid()); // Provide required releaseMbid
        $this->persistEntity($album);

        return $album;
    }

    protected function createTestTrack(string $title, Album $album): Track
    {
        $track = new Track();
        $track->setTitle($title);
        $track->setAlbum($album);
        $this->persistEntity($track);

        return $track;
    }
}
