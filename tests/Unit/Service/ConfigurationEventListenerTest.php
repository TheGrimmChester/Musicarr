<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Configuration\Event\ConfigurationAfterDeleteEvent;
use App\Configuration\Event\ConfigurationAfterGetEvent;
use App\Configuration\Event\ConfigurationAfterSetEvent;
use App\Configuration\Event\ConfigurationBeforeDeleteEvent;
use App\Configuration\Event\ConfigurationBeforeGetEvent;
use App\Configuration\Event\ConfigurationBeforeSetEvent;
use App\Configuration\EventListener\ConfigurationEventListener;
use App\Configuration\EventListener\ConfigurationValidationListener;
use App\Entity\Configuration;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Contracts\Cache\CacheInterface;

class ConfigurationEventListenerTest extends TestCase
{
    private ConfigurationEventListener $basicListener;
    private ConfigurationValidationListener $validationListener;
    private LoggerInterface|MockObject $logger;
    private ConfigurationDomainRegistry|MockObject $domainRegistry;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->domainRegistry = $this->createMock(ConfigurationDomainRegistry::class);

        $this->basicListener = new ConfigurationEventListener($this->logger);
        $this->validationListener = new ConfigurationValidationListener(
            $this->logger,
            $this->createMock(CacheInterface::class),
            $this->domainRegistry
        );
    }

    private function createConfiguration(string $key, string $value): Configuration
    {
        $configuration = new Configuration();
        $configuration->setKey($key);
        $configuration->setValue($value);

        return $configuration;
    }

    public function testConfigurationEventListenerImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->basicListener);
    }

    public function testConfigurationEventListenerGetSubscribedEvents(): void
    {
        $subscribedEvents = ConfigurationEventListener::getSubscribedEvents();

        $this->assertArrayHasKey(ConfigurationBeforeSetEvent::NAME, $subscribedEvents);
        $this->assertArrayHasKey(ConfigurationAfterSetEvent::NAME, $subscribedEvents);
        $this->assertArrayHasKey(ConfigurationBeforeDeleteEvent::NAME, $subscribedEvents);
        $this->assertArrayHasKey(ConfigurationAfterDeleteEvent::NAME, $subscribedEvents);
        $this->assertArrayHasKey(ConfigurationBeforeGetEvent::NAME, $subscribedEvents);
        $this->assertArrayHasKey(ConfigurationAfterGetEvent::NAME, $subscribedEvents);

        $this->assertEquals('onConfigurationBeforeSet', $subscribedEvents[ConfigurationBeforeSetEvent::NAME]);
        $this->assertEquals('onConfigurationAfterSet', $subscribedEvents[ConfigurationAfterSetEvent::NAME]);
        $this->assertEquals('onConfigurationBeforeDelete', $subscribedEvents[ConfigurationBeforeDeleteEvent::NAME]);
        $this->assertEquals('onConfigurationAfterDelete', $subscribedEvents[ConfigurationAfterDeleteEvent::NAME]);
        $this->assertEquals('onConfigurationBeforeGet', $subscribedEvents[ConfigurationBeforeGetEvent::NAME]);
        $this->assertEquals('onConfigurationAfterGet', $subscribedEvents[ConfigurationAfterGetEvent::NAME]);
    }

    public function testConfigurationEventListenerLogsBeforeSetEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('test_value');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'test.key',
            'new_value',
            'Updated description'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration value will be set',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'new_value' === $context['value']
                           && 'Updated description' === $context['description'];
                })
            );

        $this->basicListener->onConfigurationBeforeSet($event);
    }

    public function testConfigurationEventListenerLogsAfterSetEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('new_value');

        $event = new ConfigurationAfterSetEvent(
            $configuration,
            'test.key',
            'new_value',
            'Updated description'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration value has been set',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'new_value' === $context['value']
                           && 'Updated description' === $context['description'];
                })
            );

        $this->basicListener->onConfigurationAfterSet($event);
    }

    public function testConfigurationEventListenerLogsBeforeDeleteEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('test_value');

        $event = new ConfigurationBeforeDeleteEvent(
            $configuration,
            'test.key',
            'test_value',
            'Test description'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration will be deleted',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'test_value' === $context['value']
                           && 'Test description' === $context['description'];
                })
            );

        $this->basicListener->onConfigurationBeforeDelete($event);
    }

    public function testConfigurationEventListenerLogsAfterDeleteEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('test_value');

        $event = new ConfigurationAfterDeleteEvent(
            $configuration,
            'test.key',
            'test_value',
            'Test description'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration has been deleted',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'test_value' === $context['value']
                           && 'Test description' === $context['description'];
                })
            );

        $this->basicListener->onConfigurationAfterDelete($event);
    }

    public function testConfigurationEventListenerLogsBeforeGetEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('test_value');

        $event = new ConfigurationBeforeGetEvent(
            $configuration,
            'test.key',
            'test_value',
            'default_value',
            'Test description'
        );

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Configuration value will be retrieved',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'test_value' === $context['value']
                           && 'default_value' === $context['default_value'];
                })
            );

        $this->basicListener->onConfigurationBeforeGet($event);
    }

    public function testConfigurationEventListenerLogsAfterGetEvent(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('test.key');
        $configuration->setValue('test_value');

        $event = new ConfigurationAfterGetEvent(
            $configuration,
            'test.key',
            'test_value',
            'default_value',
            'final_value',
            'Test description'
        );

        $this->logger
            ->expects($this->once())
            ->method('debug')
            ->with(
                'Configuration value has been retrieved',
                $this->callback(function (array $context) {
                    return 'test.key' === $context['key']
                           && 'test_value' === $context['value']
                           && 'default_value' === $context['default_value']
                           && 'final_value' === $context['final_value'];
                })
            );

        $this->basicListener->onConfigurationAfterGet($event);
    }

    public function testConfigurationEventListenerLogsNullConfiguration(): void
    {
        $configuration = new Configuration();
        $configuration->setKey('new.key');
        $configuration->setValue('old_value');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'new.key',
            'new_value',
            'New configuration'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration value will be set',
                $this->callback(function (array $context) {
                    return 'new.key' === $context['key']
                           && 'new_value' === $context['value']
                           && 'New configuration' === $context['description'];
                })
            );

        $this->basicListener->onConfigurationBeforeSet($event);
    }

    public function testConfigurationEventListenerLogsComplexValues(): void
    {
        $complexValue = [
            'nested' => [
                'key' => 'value',
                'array' => [1, 2, 3],
            ],
        ];

        $configuration = new Configuration();
        $configuration->setKey('complex.key');
        $configuration->setValue('old_complex_value');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'complex.key',
            $complexValue,
            'Complex configuration'
        );

        $this->logger
            ->expects($this->once())
            ->method('info')
            ->with(
                'Configuration value will be set',
                $this->callback(function (array $context) use ($complexValue) {
                    return 'complex.key' === $context['key']
                           && $context['value'] === $complexValue;
                })
            );

        $this->basicListener->onConfigurationBeforeSet($event);
    }

    public function testConfigurationValidationListenerImplementsEventSubscriberInterface(): void
    {
        $this->assertInstanceOf(EventSubscriberInterface::class, $this->validationListener);
    }

    public function testConfigurationValidationListenerGetSubscribedEvents(): void
    {
        $subscribedEvents = ConfigurationValidationListener::getSubscribedEvents();

        $this->assertArrayHasKey(ConfigurationBeforeSetEvent::NAME, $subscribedEvents);
        $this->assertEquals('onConfigurationBeforeSet', $subscribedEvents[ConfigurationBeforeSetEvent::NAME]);
    }

    public function testConfigurationValidationListenerValidatesConfiguration(): void
    {
        // Mock validation rules from domain registry
        $validationRules = [
            'downloader.enabled' => 'boolean',
            'downloader.search_timeout' => 'integer|min:10|max:300',
            'downloader.max_concurrent_downloads' => 'integer|min:1|max:10',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $configuration = new Configuration();
        $configuration->setKey('downloader.enabled');
        $configuration->setParsedValue(false);

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'downloader.enabled',
            true,
            'Enable downloader'
        );

        // Should not throw exception for valid boolean value
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerValidatesIntegerRange(): void
    {
        $validationRules = [
            'downloader.search_timeout' => 'integer|min:10|max:300',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $configuration = new Configuration();
        $configuration->setKey('downloader.search_timeout');
        $configuration->setParsedValue(30);

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'downloader.search_timeout',
            60,
            'Search timeout'
        );

        // Should not throw exception for valid integer value
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerValidatesArrayValues(): void
    {
        $validationRules = [
            'downloader.quality_preferences' => 'array',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $configuration = new Configuration();
        $configuration->setKey('downloader.quality_preferences');
        $configuration->setValue('[]');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'downloader.quality_preferences',
            ['FLAC', 'MP3 320'],
            'Quality preferences'
        );

        // Should not throw exception for valid array value
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerHandlesNoValidationRules(): void
    {
        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn([]);

        $configuration = new Configuration();
        $configuration->setKey('unknown.key');
        $configuration->setValue('unknown_value');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'unknown.key',
            'unknown_value',
            'Unknown configuration'
        );

        // Should not throw exception when no validation rules exist
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerHandlesNullValues(): void
    {
        $validationRules = [
            'downloader.enabled' => 'boolean',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $configuration = new Configuration();
        $configuration->setKey('downloader.enabled');
        $configuration->setValue('0');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'downloader.enabled',
            null,
            'Enable downloader'
        );

        // Should not throw exception for null values
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerHandlesEmptyValues(): void
    {
        $validationRules = [
            'downloader.host' => 'string',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $configuration = new Configuration();
        $configuration->setKey('downloader.host');
        $configuration->setValue('');

        $event = new ConfigurationBeforeSetEvent(
            $configuration,
            'downloader.host',
            '',
            'Host configuration'
        );

        // Should not throw exception for empty string values
        $this->validationListener->onConfigurationBeforeSet($event);
        $this->assertTrue(true); // Test passed if no exception
    }

    public function testConfigurationValidationListenerHandlesComplexValidationRules(): void
    {
        $validationRules = [
            'downloader.port' => 'integer|min:1|max:65535',
            'downloader.score' => 'float|min:0|max:100',
            'downloader.name' => 'string|min:1|max:255',
        ];

        $this->domainRegistry
            ->method('getAllValidationRules')
            ->willReturn($validationRules);

        $events = [
            new ConfigurationBeforeSetEvent($this->createConfiguration('downloader.port', '8080'), 'downloader.port', 8080, 'Port'),
            new ConfigurationBeforeSetEvent($this->createConfiguration('downloader.score', '85.5'), 'downloader.score', 85.5, 'Score'),
            new ConfigurationBeforeSetEvent($this->createConfiguration('downloader.name', 'Test Name'), 'downloader.name', 'Test Name', 'Name'),
        ];

        foreach ($events as $event) {
            // Should not throw exception for valid values
            $this->validationListener->onConfigurationBeforeSet($event);
        }

        $this->assertTrue(true); // Test passed if no exception
    }
}
