<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ConfigurationRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;
    private ConfigurationRepository $configurationRepository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = $this->getContainer()->get(EntityManagerInterface::class);
        $this->configurationRepository = $this->entityManager->getRepository(Configuration::class);

        // Clear database before each test
        $this->clearDatabase();
    }

    protected function tearDown(): void
    {
        $this->clearDatabase();
        parent::tearDown();
    }

    public function testSaveConfiguration(): void
    {
        $config = new Configuration();
        $config->setKey('test.key');
        $config->setParsedValue('test value');

        $this->configurationRepository->save($config, true);

        $this->assertNotNull($config->getId());
        $this->assertEquals('test.key', $config->getKey());
        $this->assertEquals('test value', $config->getParsedValue());
    }

    public function testSaveConfigurationWithoutFlush(): void
    {
        $config = new Configuration();
        $config->setKey('test.key');
        $config->setParsedValue('test value');

        $this->configurationRepository->save($config, false);

        // Should not have ID yet since flush wasn't called
        $this->assertNull($config->getId());

        // Now flush manually
        $this->entityManager->flush();

        $this->assertNotNull($config->getId());
    }

    public function testRemoveConfiguration(): void
    {
        $config = $this->createTestConfiguration('test.key', 'test value');
        $configId = $config->getId();

        $this->configurationRepository->remove($config, true);

        $this->assertNull($this->configurationRepository->find($configId));
    }

    public function testRemoveConfigurationWithoutFlush(): void
    {
        $config = $this->createTestConfiguration('test.key', 'test value');
        $configId = $config->getId();

        $this->configurationRepository->remove($config, false);

        // Should still exist since flush wasn't called
        $this->assertNotNull($this->configurationRepository->find($configId));

        // Now flush manually
        $this->entityManager->flush();

        $this->assertNull($this->configurationRepository->find($configId));
    }

    public function testFindByKey(): void
    {
        $config = $this->createTestConfiguration('test.key', 'test value');

        $foundConfig = $this->configurationRepository->findByKey('test.key');

        $this->assertNotNull($foundConfig);
        $this->assertEquals($config->getId(), $foundConfig->getId());
        $this->assertEquals('test.key', $foundConfig->getKey());
        $this->assertEquals('test value', $foundConfig->getParsedValue());
    }

    public function testFindByKeyReturnsNullWhenNotFound(): void
    {
        $foundConfig = $this->configurationRepository->findByKey('non.existent.key');

        $this->assertNull($foundConfig);
    }

    public function testGetAllAsArray(): void
    {
        $this->createTestConfiguration('key1', 'value1');
        $this->createTestConfiguration('key2', 'value2');
        $this->createTestConfiguration('key3', 'value3');

        $result = $this->configurationRepository->getAllAsArray();

        $this->assertCount(3, $result);
        $this->assertEquals('value1', $result['key1']);
        $this->assertEquals('value2', $result['key2']);
        $this->assertEquals('value3', $result['key3']);
    }

    public function testGetAllAsArrayReturnsEmptyArrayWhenNoConfigurations(): void
    {
        $result = $this->configurationRepository->getAllAsArray();

        $this->assertEmpty($result);
    }

    public function testFindByKeyPrefix(): void
    {
        $this->createTestConfiguration('app.name', 'My App');
        $this->createTestConfiguration('app.version', '1.0.0');
        $this->createTestConfiguration('app.debug', true);
        $this->createTestConfiguration('database.host', 'localhost');

        $results = $this->configurationRepository->findByKeyPrefix('app.');

        $this->assertCount(3, $results);

        $keys = array_map(fn ($config) => $config->getKey(), $results);
        $this->assertContains('app.name', $keys);
        $this->assertContains('app.version', $keys);
        $this->assertContains('app.debug', $keys);
        $this->assertNotContains('database.host', $keys);
    }

    public function testFindByKeyPrefixOrdersByKey(): void
    {
        $this->createTestConfiguration('app.zebra', 'Zebra');
        $this->createTestConfiguration('app.alpha', 'Alpha');
        $this->createTestConfiguration('app.beta', 'Beta');

        $results = $this->configurationRepository->findByKeyPrefix('app.');

        $this->assertCount(3, $results);
        $this->assertEquals('app.alpha', $results[0]->getKey());
        $this->assertEquals('app.beta', $results[1]->getKey());
        $this->assertEquals('app.zebra', $results[2]->getKey());
    }

    public function testFindByKeyPrefixReturnsEmptyArrayWhenNoMatches(): void
    {
        $this->createTestConfiguration('app.name', 'My App');

        $results = $this->configurationRepository->findByKeyPrefix('database.');

        $this->assertEmpty($results);
    }

    public function testFindByKeyPrefixWithEmptyPrefix(): void
    {
        $this->createTestConfiguration('app.name', 'My App');
        $this->createTestConfiguration('database.host', 'localhost');

        $results = $this->configurationRepository->findByKeyPrefix('');

        $this->assertCount(2, $results);
    }

    public function testFindConfigurationsByPrefix(): void
    {
        $this->createTestConfiguration('app.name', 'My App');
        $this->createTestConfiguration('app.version', '1.0.0');
        $this->createTestConfiguration('app.debug', true);
        $this->createTestConfiguration('database.host', 'localhost');

        $results = $this->configurationRepository->findConfigurationsByPrefix('app.');

        $this->assertCount(3, $results);

        $keys = array_map(fn ($config) => $config->getKey(), $results);
        $this->assertContains('app.name', $keys);
        $this->assertContains('app.version', $keys);
        $this->assertContains('app.debug', $keys);
        $this->assertNotContains('database.host', $keys);
    }

    public function testFindConfigurationsByPrefixOrdersByKey(): void
    {
        $this->createTestConfiguration('app.zebra', 'Zebra');
        $this->createTestConfiguration('app.alpha', 'Alpha');
        $this->createTestConfiguration('app.beta', 'Beta');

        $results = $this->configurationRepository->findConfigurationsByPrefix('app.');

        $this->assertCount(3, $results);
        $this->assertEquals('app.alpha', $results[0]->getKey());
        $this->assertEquals('app.beta', $results[1]->getKey());
        $this->assertEquals('app.zebra', $results[2]->getKey());
    }

    public function testFindConfigurationsByPrefixReturnsEmptyArrayWhenNoMatches(): void
    {
        $this->createTestConfiguration('app.name', 'My App');

        $results = $this->configurationRepository->findConfigurationsByPrefix('database.');

        $this->assertEmpty($results);
    }

    public function testClearAll(): void
    {
        $this->createTestConfiguration('key1', 'value1');
        $this->createTestConfiguration('key2', 'value2');
        $this->createTestConfiguration('key3', 'value3');

        $this->assertCount(3, $this->configurationRepository->findAll());

        $this->configurationRepository->clearAll();

        $this->assertCount(0, $this->configurationRepository->findAll());
    }

    public function testClearAllWithNoConfigurations(): void
    {
        $this->assertCount(0, $this->configurationRepository->findAll());

        $this->configurationRepository->clearAll();

        $this->assertCount(0, $this->configurationRepository->findAll());
    }

    public function testConfigurationPersistence(): void
    {
        $config = new Configuration();
        $config->setKey('persistence.test');
        $config->setParsedValue('persistent value');

        $this->configurationRepository->save($config, true);

        // Clear entity manager to test persistence
        $this->clearEntityManager();

        $foundConfig = $this->configurationRepository->findByKey('persistence.test');

        $this->assertNotNull($foundConfig);
        $this->assertEquals('persistent value', $foundConfig->getParsedValue());
    }

    public function testConfigurationUpdate(): void
    {
        $config = $this->createTestConfiguration('update.test', 'original value');

        $config->setParsedValue('updated value');
        $this->configurationRepository->save($config, true);

        $this->refreshEntity($config);

        $this->assertEquals('updated value', $config->getParsedValue());
    }

    public function testConfigurationWithComplexValues(): void
    {
        $complexValue = [
            'nested' => [
                'key' => 'value',
                'array' => [1, 2, 3],
                'boolean' => true,
                'null' => null,
            ],
        ];

        $config = new Configuration();
        $config->setKey('complex.config');
        $config->setParsedValue($complexValue);

        $this->configurationRepository->save($config, true);

        $foundConfig = $this->configurationRepository->findByKey('complex.config');

        $this->assertNotNull($foundConfig);
        $this->assertEquals($complexValue, $foundConfig->getParsedValue());
    }

    private function createTestConfiguration(string $key, $value): Configuration
    {
        $config = new Configuration();
        $config->setKey($key);
        $config->setParsedValue($value);

        $this->persistEntity($config);

        return $config;
    }

    private function persistEntity($entity): void
    {
        $this->entityManager->persist($entity);
        $this->entityManager->flush();
    }

    private function clearDatabase(): void
    {
        $this->entityManager->createQuery('DELETE FROM App\Entity\Configuration')->execute();
        $this->entityManager->flush();
    }

    private function clearEntityManager(): void
    {
        $this->entityManager->clear();
    }

    private function refreshEntity($entity): void
    {
        $this->entityManager->refresh($entity);
    }
}
