<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Exception;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

abstract class AbstractRepositoryTestCase extends WebTestCase
{
    protected EntityManagerInterface $entityManager;
    protected ?KernelBrowser $client;

    private static bool $schemaCreated = false;

    protected function setUp(): void
    {
        // Create client only once to bootstrap the kernel
        if (!self::$schemaCreated) {
            $this->client = static::createClient();
            $this->createDatabaseSchema();
            self::$schemaCreated = true;
        } else {
            // For subsequent tests, just get the container without creating a new client
            $this->client = null;
        }

        // Get services
        $container = $this->getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Always get a fresh EntityManager to avoid closed EntityManager issues
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $container->get(EntityManagerInterface::class);
        }

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();

        // No need to clean up database file since we're using in-memory database

        parent::tearDown();
    }

    protected function clearDatabase(): void
    {
        // Disable foreign key checks temporarily
        $this->entityManager->getConnection()->executeQuery('PRAGMA foreign_keys = OFF');

        // Clear all entities in the correct order to avoid foreign key constraint issues
        // Start with entities that have foreign key dependencies
        $this->entityManager->createQuery('DELETE FROM App\Entity\UnmatchedTrack')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\TrackFile')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Track')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Medium')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\AlbumStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\ArtistStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\LibraryStatistic')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Task')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Album')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Artist')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Library')->execute();
        $this->entityManager->createQuery('DELETE FROM App\Entity\Configuration')->execute();

        $this->entityManager->flush();

        // Re-enable foreign key checks
        $this->entityManager->getConnection()->executeQuery('PRAGMA foreign_keys = ON');
    }

    private function createDatabaseSchema(): void
    {
        $container = $this->getContainer();
        $entityManager = $container->get(EntityManagerInterface::class);

        // Create schema
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);

        // Drop existing schema first to avoid conflicts
        try {
            $schemaTool->dropSchema($metadata);
        } catch (Exception $e) {
            // Ignore errors when dropping schema
        }

        $schemaTool->createSchema($metadata);
    }

    /**
     * Helper method to create and persist a test entity.
     */
    protected function persistEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    /**
     * Helper method to refresh an entity from the database.
     */
    protected function refreshEntity($entity): void
    {
        $this->entityManager->refresh($entity);
    }

    /**
     * Helper method to remove an entity from the database.
     */
    protected function removeEntity($entity): void
    {
        $this->entityManager->remove($entity);
        $this->entityManager->flush();
    }

    /**
     * Helper method to clear the entity manager.
     */
    protected function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }

    /**
     * Helper method to count entities of a specific class.
     */
    protected function countEntities(string $entityClass): int
    {
        return $this->entityManager->getRepository($entityClass)->count([]);
    }

    /**
     * Helper method to find all entities of a specific class.
     */
    protected function findAllEntities(string $entityClass): array
    {
        return $this->entityManager->getRepository($entityClass)->findAll();
    }

    /**
     * Helper method to find an entity by ID.
     */
    protected function findEntityById(string $entityClass, int $id)
    {
        return $this->entityManager->getRepository($entityClass)->find($id);
    }
}
