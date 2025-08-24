<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Library;
use App\Entity\UnmatchedTrack;
use App\Repository\UnmatchedTrackRepository;
use DateTime;

class UnmatchedTrackRepositoryTest extends AbstractRepositoryTestCase
{
    private UnmatchedTrackRepository $unmatchedTrackRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->unmatchedTrackRepository = $this->entityManager->getRepository(UnmatchedTrack::class);
    }

    public function testSaveUnmatchedTrack(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);

        $this->unmatchedTrackRepository->save($track, true);

        $this->assertNotNull($track->getId());
        $this->assertEquals('Test Artist', $track->getArtist());
        $this->assertEquals('Test Title', $track->getTitle());
        $this->assertEquals($library, $track->getLibrary());
    }

    public function testSaveUnmatchedTrackWithoutFlush(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = new UnmatchedTrack();
        $track->setArtist('Test Artist');
        $track->setTitle('Test Title');
        $track->setLibrary($library);
        $track->setFilePath('/test/path/test.mp3');
        $track->setDiscoveredAt(new DateTime());
        $track->setIsMatched(false);

        $this->unmatchedTrackRepository->save($track, false);

        // Should not have ID yet since flush wasn't called
        $this->assertNull($track->getId());

        // Now flush manually
        $this->entityManager->flush();

        $this->assertNotNull($track->getId());
    }

    public function testRemoveUnmatchedTrack(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);
        $trackId = $track->getId();

        $this->unmatchedTrackRepository->remove($track, true);

        $this->assertNull($this->unmatchedTrackRepository->find($trackId));
    }

    public function testRemoveUnmatchedTrackWithoutFlush(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);
        $trackId = $track->getId();

        $this->unmatchedTrackRepository->remove($track, false);

        // Should still exist since flush wasn't called
        $this->assertNotNull($this->unmatchedTrackRepository->find($trackId));

        // Now flush manually
        $this->entityManager->flush();

        $this->assertNull($this->unmatchedTrackRepository->find($trackId));
    }

    public function testFindUnmatchedByLibrary(): void
    {
        $library1 = $this->createTestLibrary('Library 1');
        $library2 = $this->createTestLibrary('Library 2');

        $track1 = $this->createTestUnmatchedTrack('Artist 1', 'Title 1', $library1);
        $track1->setDiscoveredAt(new DateTime('2023-01-01 10:00:00'));
        $track2 = $this->createTestUnmatchedTrack('Artist 2', 'Title 2', $library1);
        $track2->setDiscoveredAt(new DateTime('2023-01-01 09:00:00'));
        $track3 = $this->createTestUnmatchedTrack('Artist 3', 'Title 3', $library2);
        $track4 = $this->createTestUnmatchedTrack('Artist 4', 'Title 4', $library1);
        $track4->setIsMatched(true); // This should be excluded

        // Ensure the change is persisted
        $this->entityManager->flush();

        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary($library1->getId());

        $this->assertCount(2, $unmatchedTracks);
        // Should be ordered by discoveredAt DESC
        $this->assertEquals($track1->getId(), $unmatchedTracks[0]->getId()); // Discovered at 10:00
        $this->assertEquals($track2->getId(), $unmatchedTracks[1]->getId()); // Discovered at 09:00
        $this->assertNotContains($track3, $unmatchedTracks); // Different library
        $this->assertNotContains($track4, $unmatchedTracks); // Already matched
    }

    public function testFindUnmatchedByLibraryReturnsEmptyArrayWhenNoUnmatchedTracks(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);
        $track->setIsMatched(true);

        // Ensure the change is persisted
        $this->entityManager->flush();

        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary($library->getId());

        $this->assertEmpty($unmatchedTracks);
    }

    public function testFindUnmatchedByLibraryReturnsEmptyArrayWhenLibraryNotFound(): void
    {
        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary(99999);

        $this->assertEmpty($unmatchedTracks);
    }

    public function testFindByArtistAndTitle(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track1 = $this->createTestUnmatchedTrack('John Doe', 'Song 1', $library);
        $track1->setDiscoveredAt(new DateTime('2023-01-01 10:00:00'));
        $track2 = $this->createTestUnmatchedTrack('Jane Doe', 'Song 2', $library);
        $track2->setDiscoveredAt(new DateTime('2023-01-01 09:00:00'));
        $track3 = $this->createTestUnmatchedTrack('Bob Smith', 'Song 3', $library);
        $track4 = $this->createTestUnmatchedTrack('John Doe', 'Song 4', $library);
        $track4->setIsMatched(true); // This should be excluded

        // Ensure the change is persisted
        $this->entityManager->flush();

        $results = $this->unmatchedTrackRepository->findByArtistAndTitle('Doe', null);

        $this->assertCount(2, $results);
        // Should be ordered by discoveredAt DESC
        $this->assertEquals($track1->getId(), $results[0]->getId()); // Discovered at 10:00
        $this->assertEquals($track2->getId(), $results[1]->getId()); // Discovered at 09:00
        $this->assertNotContains($track3, $results); // Different artist
        $this->assertNotContains($track4, $results); // Already matched
    }

    public function testFindByArtistAndTitleWithTitleFilter(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track1 = $this->createTestUnmatchedTrack('Artist 1', 'Song 1', $library);
        $track2 = $this->createTestUnmatchedTrack('Artist 2', 'Song 2', $library);
        $track3 = $this->createTestUnmatchedTrack('Artist 3', 'Song 1', $library);

        $results = $this->unmatchedTrackRepository->findByArtistAndTitle(null, 'Song 1');

        $this->assertCount(2, $results);
        $this->assertContains($track1, $results);
        $this->assertContains($track3, $results);
        $this->assertNotContains($track2, $results);
    }

    public function testFindByArtistAndTitleWithBothFilters(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track1 = $this->createTestUnmatchedTrack('John Doe', 'Song 1', $library);
        $track2 = $this->createTestUnmatchedTrack('John Doe', 'Song 2', $library);
        $track3 = $this->createTestUnmatchedTrack('Jane Doe', 'Song 1', $library);

        $results = $this->unmatchedTrackRepository->findByArtistAndTitle('John', 'Song 1');

        $this->assertCount(1, $results);
        $this->assertEquals($track1->getId(), $results[0]->getId());
    }

    public function testFindByArtistAndTitleWithNoFilters(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track1 = $this->createTestUnmatchedTrack('Artist 1', 'Song 1', $library);
        $track2 = $this->createTestUnmatchedTrack('Artist 2', 'Song 2', $library);

        $results = $this->unmatchedTrackRepository->findByArtistAndTitle(null, null);

        $this->assertCount(2, $results);
        $this->assertContains($track1, $results);
        $this->assertContains($track2, $results);
    }

    public function testFindByArtistAndTitleIsCaseInsensitive(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('John Doe', 'Song Title', $library);

        $results = $this->unmatchedTrackRepository->findByArtistAndTitle('john', 'song');

        $this->assertCount(1, $results);
        $this->assertEquals($track->getId(), $results[0]->getId());
    }

    public function testFindByFilePath(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);
        $track->setFilePath('/path/to/test/file.mp3');

        // Persist the file path change
        $this->entityManager->flush();

        $foundTrack = $this->unmatchedTrackRepository->findByFilePath('/path/to/test/file.mp3');

        $this->assertNotNull($foundTrack);
        $this->assertEquals($track->getId(), $foundTrack->getId());
        $this->assertEquals('/path/to/test/file.mp3', $foundTrack->getFilePath());
    }

    public function testFindByFilePathReturnsNullWhenNotFound(): void
    {
        $foundTrack = $this->unmatchedTrackRepository->findByFilePath('/non/existent/path.mp3');

        $this->assertNull($foundTrack);
    }

    public function testCountUnmatchedByLibrary(): void
    {
        $library1 = $this->createTestLibrary('Library 1');
        $library2 = $this->createTestLibrary('Library 2');

        $this->createTestUnmatchedTrack('Artist 1', 'Title 1', $library1);
        $this->createTestUnmatchedTrack('Artist 2', 'Title 2', $library1);
        $this->createTestUnmatchedTrack('Artist 3', 'Title 3', $library2);
        $track4 = $this->createTestUnmatchedTrack('Artist 4', 'Title 4', $library1);
        $track4->setIsMatched(true); // This should be excluded

        // Ensure the change is persisted
        $this->entityManager->flush();

        $count = $this->unmatchedTrackRepository->countUnmatchedByLibrary($library1->getId());

        $this->assertEquals(2, $count);
    }

    public function testCountUnmatchedByLibraryReturnsZeroWhenNoUnmatchedTracks(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Test Artist', 'Test Title', $library);
        $track->setIsMatched(true);

        // Ensure the change is persisted
        $this->entityManager->flush();

        $count = $this->unmatchedTrackRepository->countUnmatchedByLibrary($library->getId());

        $this->assertEquals(0, $count);
    }

    public function testCountUnmatchedByLibraryReturnsZeroWhenLibraryNotFound(): void
    {
        $count = $this->unmatchedTrackRepository->countUnmatchedByLibrary(99999);

        $this->assertEquals(0, $count);
    }

    public function testUnmatchedTrackPersistence(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = new UnmatchedTrack();
        $track->setArtist('Persistence Artist');
        $track->setTitle('Persistence Title');
        $track->setLibrary($library);
        $track->setFilePath('/path/to/persistence.mp3');
        $track->setDiscoveredAt(new DateTime());

        $this->unmatchedTrackRepository->save($track, true);

        $this->assertNotNull($track->getId());

        // Clear entity manager to test persistence
        $this->clearEntityManager();

        $foundTrack = $this->unmatchedTrackRepository->find($track->getId());

        $this->assertNotNull($foundTrack);
        $this->assertEquals('Persistence Artist', $foundTrack->getArtist());
        $this->assertEquals('Persistence Title', $foundTrack->getTitle());
        $this->assertEquals('/path/to/persistence.mp3', $foundTrack->getFilePath());
        $this->assertEquals($library->getId(), $foundTrack->getLibrary()->getId());
    }

    public function testUnmatchedTrackUpdate(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack('Original Artist', 'Original Title', $library);

        $track->setArtist('Updated Artist');
        $track->setTitle('Updated Title');
        $this->unmatchedTrackRepository->save($track, true);

        $this->refreshEntity($track);

        $this->assertEquals('Updated Artist', $track->getArtist());
        $this->assertEquals('Updated Title', $track->getTitle());
    }

    public function testUnmatchedTrackWithSpecialCharacters(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $track = $this->createTestUnmatchedTrack("Artist with 'quotes'", 'Title with "double quotes"', $library);
        $track->setFilePath('/path/with/special/chars/&%$#.mp3');

        // Persist the file path change
        $this->entityManager->flush();

        $foundTrack = $this->unmatchedTrackRepository->findByFilePath('/path/with/special/chars/&%$#.mp3');

        $this->assertNotNull($foundTrack);
        $this->assertEquals("Artist with 'quotes'", $foundTrack->getArtist());
        $this->assertEquals('Title with "double quotes"', $foundTrack->getTitle());
    }

    protected function createTestLibrary(string $name): Library
    {
        $library = new Library();
        $library->setName($name);
        $library->setPath('/test/path');
        $this->persistEntity($library);

        return $library;
    }

    protected function createTestUnmatchedTrack(?string $artist, ?string $title, Library $library): UnmatchedTrack
    {
        $track = new UnmatchedTrack();
        $track->setArtist($artist);
        $track->setTitle($title);
        $track->setLibrary($library);
        // Generate unique file path to avoid unique constraint violations
        $track->setFilePath('/test/path/' . uniqid() . '_' . ($title ?? 'untitled') . '.mp3');
        $track->setDiscoveredAt(new DateTime());
        $track->setIsMatched(false);
        $this->persistEntity($track);

        return $track;
    }
}
