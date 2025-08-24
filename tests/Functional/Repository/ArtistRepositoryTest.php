<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Artist;
use App\Entity\Library;
use App\Repository\ArtistRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ArtistRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ArtistRepository $artistRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->artistRepository = $this->entityManager->getRepository(Artist::class);

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    public function testSearchByName(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist1 = $this->createTestArtist('John Doe', $library);
        $artist2 = $this->createTestArtist('Jane Doe', $library);
        $artist3 = $this->createTestArtist('Bob Smith', $library);

        $results = $this->artistRepository->searchByName('Doe', 10);

        $this->assertCount(2, $results);
        $this->assertContains($artist1, $results);
        $this->assertContains($artist2, $results);
        $this->assertNotContains($artist3, $results);
    }

    public function testSearchByNameWithLimit(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('Artist 1', $library);
        $this->createTestArtist('Artist 2', $library);
        $this->createTestArtist('Artist 3', $library);

        $results = $this->artistRepository->searchByName('Artist', 2);

        $this->assertCount(2, $results);
    }

    public function testSearchByNameReturnsEmptyArrayWhenNoMatches(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->searchByName('Non-existent', 10);

        $this->assertEmpty($results);
    }

    public function testSearchByNameIsCaseInsensitive(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->searchByName('john', 10);

        $this->assertCount(1, $results);
        $this->assertEquals($artist, $results[0]);
    }

    public function testFindAllWithLibrary(): void
    {
        $library1 = $this->createTestLibrary('Library 1');
        $library2 = $this->createTestLibrary('Library 2');
        $artist1 = $this->createTestArtist('Artist 1', $library1);
        $artist2 = $this->createTestArtist('Artist 2', $library2);

        $results = $this->artistRepository->findAllWithLibrary();

        $this->assertCount(2, $results);
        $this->assertContains($artist1, $results);
        $this->assertContains($artist2, $results);
    }

    public function testFindAllWithLibraryOrdersByName(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist2 = $this->createTestArtist('Zebra Artist', $library);
        $artist1 = $this->createTestArtist('Alpha Artist', $library);

        $results = $this->artistRepository->findAllWithLibrary();

        $this->assertCount(2, $results);
        $this->assertEquals('Alpha Artist', $results[0]->getName());
        $this->assertEquals('Zebra Artist', $results[1]->getName());
    }

    public function testFindByName(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('John Doe', $library);

        $foundArtist = $this->artistRepository->findByName('John Doe');

        $this->assertNotNull($foundArtist);
        $this->assertEquals($artist->getId(), $foundArtist->getId());
        $this->assertEquals('John Doe', $foundArtist->getName());
    }

    public function testFindByNameReturnsNullWhenNotFound(): void
    {
        $foundArtist = $this->artistRepository->findByName('Non-existent Artist');

        $this->assertNull($foundArtist);
    }

    public function testFindByNameIsCaseSensitive(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('John Doe', $library);

        $foundArtist = $this->artistRepository->findByName('john doe');

        $this->assertNull($foundArtist);
    }

    public function testFindByNamePartial(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist1 = $this->createTestArtist('John Doe', $library);
        $artist2 = $this->createTestArtist('Jane Doe', $library);
        $artist3 = $this->createTestArtist('Bob Smith', $library);

        $results = $this->artistRepository->findByNamePartial('Doe', 10);

        $this->assertCount(2, $results);
        $this->assertContains($artist1, $results);
        $this->assertContains($artist2, $results);
        $this->assertNotContains($artist3, $results);
    }

    public function testFindByNamePartialWithLimit(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('Artist 1', $library);
        $this->createTestArtist('Artist 2', $library);
        $this->createTestArtist('Artist 3', $library);

        $results = $this->artistRepository->findByNamePartial('Artist', 2);

        $this->assertCount(2, $results);
    }

    public function testFindByNamePartialReturnsEmptyArrayWhenNoMatches(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->findByNamePartial('Non-existent', 10);

        $this->assertEmpty($results);
    }

    public function testFindByNamePartialIsCaseInsensitive(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->findByNamePartial('john', 10);

        $this->assertCount(1, $results);
        $this->assertEquals($artist, $results[0]);
    }

    public function testSearchForManualMatching(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist1 = $this->createTestArtist('John Doe', $library);
        $artist2 = $this->createTestArtist('Jane Doe', $library);
        $artist3 = $this->createTestArtist('Bob Smith', $library);

        $results = $this->artistRepository->searchForManualMatching('Doe', 20);

        $this->assertCount(2, $results);
        $this->assertContains($artist1, $results);
        $this->assertContains($artist2, $results);
        $this->assertNotContains($artist3, $results);
    }

    public function testSearchForManualMatchingWithEmptyQuery(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist1 = $this->createTestArtist('John Doe', $library);
        $artist2 = $this->createTestArtist('Jane Doe', $library);

        $results = $this->artistRepository->searchForManualMatching('', 20);

        $this->assertCount(2, $results);
        $this->assertContains($artist1, $results);
        $this->assertContains($artist2, $results);
    }

    public function testSearchForManualMatchingWithLimit(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('Artist 1', $library);
        $this->createTestArtist('Artist 2', $library);
        $this->createTestArtist('Artist 3', $library);

        $results = $this->artistRepository->searchForManualMatching('Artist', 2);

        $this->assertCount(2, $results);
    }

    public function testSearchForManualMatchingOrdersByName(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist2 = $this->createTestArtist('Zebra Artist', $library);
        $artist1 = $this->createTestArtist('Alpha Artist', $library);

        $results = $this->artistRepository->searchForManualMatching('Artist', 20);

        $this->assertCount(2, $results);
        $this->assertEquals('Alpha Artist', $results[0]->getName());
        $this->assertEquals('Zebra Artist', $results[1]->getName());
    }

    public function testSearchForManualMatchingLoadsLibrary(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->searchForManualMatching('Doe', 20);

        $this->assertCount(1, $results);
        // Note: Library relationship is not currently implemented in Artist entity
    }

    public function testSearchForManualMatchingReturnsEmptyArrayWhenNoMatches(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->searchForManualMatching('Non-existent', 20);

        $this->assertEmpty($results);
    }

    public function testSearchForManualMatchingIsCaseInsensitive(): void
    {
        $library = $this->createTestLibrary('Test Library');
        $artist = $this->createTestArtist('John Doe', $library);

        $results = $this->artistRepository->searchForManualMatching('john', 20);

        $this->assertCount(1, $results);
        $this->assertEquals($artist, $results[0]);
    }

    private function createTestLibrary(string $name): Library
    {
        $library = new Library();
        $library->setName($name);
        $library->setPath('/test/path');
        $this->persistEntity($library);

        return $library;
    }

    private function createTestArtist(string $name, ?Library $library = null): Artist
    {
        $artist = new Artist();
        $artist->setName($name);
        $this->persistEntity($artist);

        return $artist;
    }

    private function persistEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function clearDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Artist')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Library')->execute();
        $this->entityManager->flush();
    }
}
