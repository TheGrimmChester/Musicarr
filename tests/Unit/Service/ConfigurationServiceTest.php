<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Configuration\ConfigurationService;
use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ConfigurationServiceTest extends TestCase
{
    private ConfigurationService $configurationService;
    private ConfigurationRepository|MockObject $configurationRepository;
    private EntityManagerInterface|MockObject $entityManager;

    protected function setUp(): void
    {
        $this->configurationRepository = $this->createMock(ConfigurationRepository::class);
        $this->entityManager = $this->createMock(EntityManagerInterface::class);

        $this->configurationService = new ConfigurationService(
            $this->configurationRepository,
            $this->entityManager
        );
    }

    public function testGet(): void
    {
        $key = 'test.key';
        $defaultValue = 'default_value';
        $expectedValue = 'stored_value';

        $config = new Configuration();
        $config->setKey($key);
        $config->setParsedValue($expectedValue);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn($config);

        $result = $this->configurationService->get($key, $defaultValue);

        $this->assertEquals($expectedValue, $result);
    }

    public function testGetWithDefaultValue(): void
    {
        $key = 'test.key';
        $defaultValue = 'default_value';

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn(null);

        $result = $this->configurationService->get($key, $defaultValue);

        $this->assertEquals($defaultValue, $result);
    }

    public function testSet(): void
    {
        $key = 'test.key';
        $value = 'test_value';
        $description = 'Test description';

        $existingConfig = new Configuration();
        $existingConfig->setKey($key);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn($existingConfig);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->configurationService->set($key, $value, $description);

        $this->assertEquals($value, $existingConfig->getParsedValue());
        $this->assertEquals($description, $existingConfig->getDescription());
    }

    public function testSetNewConfiguration(): void
    {
        $key = 'new.key';
        $value = 'new_value';
        $description = 'New configuration description';

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Configuration $config) use ($key) {
                return $config->getKey() === $key;
            }));

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->configurationService->set($key, $value, $description);
    }

    public function testDelete(): void
    {
        $key = 'test.key';
        $config = new Configuration();
        $config->setKey($key);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn($config);

        $this->entityManager
            ->expects($this->once())
            ->method('remove')
            ->with($config);

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $result = $this->configurationService->delete($key);

        $this->assertTrue($result);
    }

    public function testDeleteNonExistentConfiguration(): void
    {
        $key = 'non.existent.key';

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn(null);

        $this->entityManager
            ->expects($this->never())
            ->method('remove');

        $this->entityManager
            ->expects($this->never())
            ->method('flush');

        $result = $this->configurationService->delete($key);

        $this->assertFalse($result);
    }

    public function testGetAll(): void
    {
        $config1 = new Configuration();
        $config1->setKey('test.key1');
        $config1->setParsedValue('value1');

        $config2 = new Configuration();
        $config2->setKey('test.key2');
        $config2->setParsedValue('value2');

        $this->configurationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$config1, $config2]);

        $result = $this->configurationService->getAll();

        $expected = [
            'test.key1' => 'value1',
            'test.key2' => 'value2',
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetConfigByPrefix(): void
    {
        $prefix = 'downloader.';

        $config1 = new Configuration();
        $config1->setKey('downloader.enabled');
        $config1->setParsedValue(true);

        $config2 = new Configuration();
        $config2->setKey('downloader.timeout');
        $config2->setParsedValue(30);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKeyPrefix')
            ->with($prefix)
            ->willReturn([$config1, $config2]);

        $result = $this->configurationService->getConfigByPrefix($prefix);

        $expected = [
            'downloader.enabled' => true,
            'downloader.timeout' => 30,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testGetByPrefix(): void
    {
        $prefix = 'metadata.';

        $config = new Configuration();
        $config->setKey('metadata.artist_image');
        $config->setParsedValue(true);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKeyPrefix')
            ->with($prefix)
            ->willReturn([$config]);

        $result = $this->configurationService->getByPrefix($prefix);

        $expected = [
            'metadata.artist_image' => true,
        ];

        $this->assertEquals($expected, $result);
    }

    public function testHas(): void
    {
        $key = 'test.key';
        $config = new Configuration();
        $config->setKey($key);

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn($config);

        $result = $this->configurationService->has($key);

        $this->assertTrue($result);
    }

    public function testHasNot(): void
    {
        $key = 'non.existent.key';

        $this->configurationRepository
            ->expects($this->once())
            ->method('findByKey')
            ->with($key)
            ->willReturn(null);

        $result = $this->configurationService->has($key);

        $this->assertFalse($result);
    }

    public function testGetAllKeys(): void
    {
        $config1 = new Configuration();
        $config1->setKey('key1');

        $config2 = new Configuration();
        $config2->setKey('key2');

        $this->configurationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$config1, $config2]);

        $result = $this->configurationService->getAllKeys();

        $expected = ['key1', 'key2'];
        $this->assertEquals($expected, $result);
    }

    public function testClearAll(): void
    {
        $config1 = new Configuration();
        $config1->setKey('key1');

        $config2 = new Configuration();
        $config2->setKey('key2');

        $this->configurationRepository
            ->expects($this->once())
            ->method('findAll')
            ->willReturn([$config1, $config2]);

        $this->entityManager
            ->expects($this->exactly(2))
            ->method('remove');

        $this->entityManager
            ->expects($this->once())
            ->method('flush');

        $this->configurationService->clearAll();
    }
}
