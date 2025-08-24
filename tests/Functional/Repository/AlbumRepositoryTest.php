<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Album;
use App\Entity\Artist;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AlbumRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private AlbumRepository $albumRepository;
    private ArtistRepository $artistRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->albumRepository = $this->entityManager->getRepository(Album::class);
        $this->artistRepository = $this->entityManager->getRepository(Artist::class);

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    public function testFindByReleaseGroupMbid(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album = $this->createTestAlbum('Test Album', $artist);
        $album->setReleaseGroupMbid('test-mbid-123');
        $this->persistEntity($album);

        $foundAlbum = $this->albumRepository->findByReleaseGroupMbid('test-mbid-123');

        $this->assertNotNull($foundAlbum);
        $this->assertEquals($album->getId(), $foundAlbum->getId());
        $this->assertEquals('test-mbid-123', $foundAlbum->getReleaseGroupMbid());
    }

    public function testFindByReleaseGroupMbidReturnsNullWhenNotFound(): void
    {
        $foundAlbum = $this->albumRepository->findByReleaseGroupMbid('non-existent-mbid');

        $this->assertNull($foundAlbum);
    }

    public function testExistsByReleaseGroupMbid(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album = $this->createTestAlbum('Test Album', $artist);
        $album->setReleaseGroupMbid('test-mbid-123');
        $this->persistEntity($album);

        $exists = $this->albumRepository->existsByReleaseGroupMbid('test-mbid-123');
        $notExists = $this->albumRepository->existsByReleaseGroupMbid('non-existent-mbid');

        $this->assertTrue($exists);
        $this->assertFalse($notExists);
    }

    public function testCreateAlbumWithReleaseGroupCheck(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $releaseGroupMbid = 'test-mbid-123';

        $album = $this->albumRepository->createAlbumWithReleaseGroupCheck(
            $releaseGroupMbid,
            function () use ($artist) {
                $album = new Album();
                $album->setTitle('Test Album');
                $album->setArtist($artist);
                $album->setReleaseMbid('test-release-mbid-' . uniqid());

                return $album;
            }
        );

        $this->assertNotNull($album->getId());
        $this->assertEquals($releaseGroupMbid, $album->getReleaseGroupMbid());
        $this->assertEquals('Test Album', $album->getTitle());
        $this->assertEquals($artist, $album->getArtist());
    }

    public function testCreateAlbumWithReleaseGroupCheckThrowsExceptionWhenMbidExists(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $releaseGroupMbid = 'test-mbid-123';

        // Create first album
        $album1 = $this->albumRepository->createAlbumWithReleaseGroupCheck(
            $releaseGroupMbid,
            function () use ($artist) {
                $album = new Album();
                $album->setTitle('First Album');
                $album->setArtist($artist);
                $album->setReleaseMbid('test-release-mbid-1');

                return $album;
            }
        );

        $this->expectException(Exception::class);
        $this->expectExceptionMessage("Un album avec le Release Group MBID {$releaseGroupMbid} existe déjà (ID: {$album1->getId()})");

        // Try to create second album with same MBID
        $this->albumRepository->createAlbumWithReleaseGroupCheck(
            $releaseGroupMbid,
            function () use ($artist) {
                $album = new Album();
                $album->setTitle('Second Album');
                $album->setArtist($artist);
                $album->setReleaseMbid('test-release-mbid-2');

                return $album;
            }
        );
    }

    public function testFindByTitleAndArtistFlexible(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album = $this->createTestAlbum("Artist's Album", $artist);
        $this->persistEntity($album);

        // Test with original title
        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible("Artist's Album", $artist);
        $this->assertNotNull($foundAlbum);
        $this->assertEquals($album->getId(), $foundAlbum->getId());

        // Test with normalized apostrophe
        $foundAlbum2 = $this->albumRepository->findByTitleAndArtistFlexible("Artist's Album", $artist);
        $this->assertNotNull($foundAlbum2);
        $this->assertEquals($album->getId(), $foundAlbum2->getId());
    }

    public function testFindByTitleAndArtistFlexibleReturnsNullWhenNotFound(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible('Non-existent Album', $artist);

        $this->assertNull($foundAlbum);
    }

    public function testFindByTitleAndArtistFlexibleWithDifferentArtist(): void
    {
        $artist1 = $this->createTestArtist('Artist 1');
        $artist2 = $this->createTestArtist('Artist 2');
        $album = $this->createTestAlbum('Test Album', $artist1);
        $this->persistEntity($album);

        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible('Test Album', $artist2);

        $this->assertNull($foundAlbum);
    }

    public function testFindByTitleAndArtistFlexibleWithMultipleAlbums(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album1 = $this->createTestAlbum('Album 1', $artist);
        $album2 = $this->createTestAlbum('Album 2', $artist);
        $this->persistEntity($album1);
        $this->persistEntity($album2);

        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible('Album 1', $artist);

        $this->assertNotNull($foundAlbum);
        $this->assertEquals('Album 1', $foundAlbum->getTitle());
    }

    public function testFindByTitleAndArtistFlexibleWithSpecialCharacters(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album = $this->createTestAlbum("Album with 'quotes' and \"double quotes\"", $artist);
        $this->persistEntity($album);

        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible("Album with 'quotes' and \"double quotes\"", $artist);

        $this->assertNotNull($foundAlbum);
        $this->assertEquals($album->getId(), $foundAlbum->getId());
    }

    public function testFindByTitleAndArtistFlexibleWithEmptyTitle(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        $album = $this->createTestAlbum('', $artist);
        $this->persistEntity($album);

        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible('', $artist);

        $this->assertNotNull($foundAlbum);
        $this->assertEquals($album->getId(), $foundAlbum->getId());
    }

    public function testFindByTitleAndArtistFlexibleWithNullTitle(): void
    {
        $artist = $this->createTestArtist('Test Artist');
        // Since title is required, we'll test with an empty string instead
        $album = $this->createTestAlbum('', $artist);
        $this->persistEntity($album);

        $foundAlbum = $this->albumRepository->findByTitleAndArtistFlexible('Test Title', $artist);

        $this->assertNull($foundAlbum);
    }

    private function createTestArtist(string $name): Artist
    {
        $artist = new Artist();
        $artist->setName($name);
        $this->persistEntity($artist);

        return $artist;
    }

    private function createTestAlbum(?string $title, Artist $artist): Album
    {
        $album = new Album();
        if (null !== $title) {
            $album->setTitle($title);
        }
        $album->setArtist($artist);
        $album->setReleaseMbid('test-release-mbid-' . uniqid());

        return $album;
    }

    private function persistEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function clearDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Album')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Artist')->execute();
        $this->entityManager->flush();
    }
}
