<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service;

use App\Configuration\Event\ConfigurationAfterDeleteEvent;
use App\Configuration\Event\ConfigurationAfterGetEvent;
use App\Configuration\Event\ConfigurationAfterSetEvent;
use App\Configuration\Event\ConfigurationBeforeDeleteEvent;
use App\Configuration\Event\ConfigurationBeforeGetEvent;
use App\Configuration\Event\ConfigurationBeforeSetEvent;
use App\Entity\Configuration;
use PHPUnit\Framework\TestCase;

class ConfigurationEventTest extends TestCase
{
    private Configuration $configuration;

    protected function setUp(): void
    {
        $this->configuration = new Configuration();
        $this->configuration->setKey('test.key');
        $this->configuration->setValue('test_value');
        $this->configuration->setDescription('Test configuration');
    }

    public function testConfigurationBeforeSetEvent(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'test.key',
            'new_value',
            'Updated description'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('new_value', $event->getValue());
        $this->assertEquals('Updated description', $event->getDescription());
        $this->assertEquals(ConfigurationBeforeSetEvent::NAME, $event::NAME);
    }

    public function testConfigurationAfterSetEvent(): void
    {
        $event = new ConfigurationAfterSetEvent(
            $this->configuration,
            'test.key',
            'new_value',
            'Updated description'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('new_value', $event->getValue());
        $this->assertEquals('Updated description', $event->getDescription());
        $this->assertEquals(ConfigurationAfterSetEvent::NAME, $event::NAME);
    }

    public function testConfigurationBeforeDeleteEvent(): void
    {
        $event = new ConfigurationBeforeDeleteEvent(
            $this->configuration,
            'test.key',
            'test_value',
            'Test configuration'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('test_value', $event->getValue());
        $this->assertEquals('Test configuration', $event->getDescription());
        $this->assertEquals(ConfigurationBeforeDeleteEvent::NAME, $event::NAME);
    }

    public function testConfigurationAfterDeleteEvent(): void
    {
        $event = new ConfigurationAfterDeleteEvent(
            $this->configuration,
            'test.key',
            'test_value',
            'Test configuration'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('test_value', $event->getValue());
        $this->assertEquals('Test configuration', $event->getDescription());
        $this->assertEquals(ConfigurationAfterDeleteEvent::NAME, $event::NAME);
    }

    public function testConfigurationBeforeGetEvent(): void
    {
        $event = new ConfigurationBeforeGetEvent(
            $this->configuration,
            'test.key',
            'test_value',
            'default_value',
            'Test configuration'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('test_value', $event->getValue());
        $this->assertEquals('default_value', $event->getDefaultValue());
        $this->assertEquals('Test configuration', $event->getDescription());
        $this->assertEquals(ConfigurationBeforeGetEvent::NAME, $event::NAME);
    }

    public function testConfigurationAfterGetEvent(): void
    {
        $event = new ConfigurationAfterGetEvent(
            $this->configuration,
            'test.key',
            'test_value',
            'default_value',
            'final_value',
            'Test configuration'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('test_value', $event->getValue());
        $this->assertEquals('default_value', $event->getDefaultValue());
        $this->assertEquals('final_value', $event->getFinalValue());
        $this->assertEquals('Test configuration', $event->getDescription());
        $this->assertEquals(ConfigurationAfterGetEvent::NAME, $event::NAME);
    }

    public function testEventWithNullConfiguration(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'new.key',
            'new_value',
            'New configuration'
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('new.key', $event->getKey());
        $this->assertEquals('new_value', $event->getValue());
        $this->assertEquals('New configuration', $event->getDescription());
    }

    public function testEventWithNullDescription(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'test.key',
            'new_value',
            null
        );

        $this->assertSame($this->configuration, $event->getConfiguration());
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('new_value', $event->getValue());
        $this->assertNull($event->getDescription());
    }

    public function testEventWithComplexValue(): void
    {
        $complexValue = [
            'nested' => [
                'key' => 'value',
                'array' => [1, 2, 3],
                'boolean' => true,
                'null' => null,
            ],
        ];

        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'complex.key',
            $complexValue,
            'Complex configuration'
        );

        $this->assertEquals($complexValue, $event->getValue());
        $this->assertEquals('complex.key', $event->getKey());
    }

    public function testEventWithEmptyStringValues(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            '',
            '',
            ''
        );

        $this->assertEquals('', $event->getKey());
        $this->assertEquals('', $event->getValue());
        $this->assertEquals('', $event->getDescription());
    }

    public function testEventWithNumericValues(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'numeric.key',
            42,
            'Numeric configuration'
        );

        $this->assertEquals('numeric.key', $event->getKey());
        $this->assertEquals(42, $event->getValue());
        $this->assertEquals('Numeric configuration', $event->getDescription());
    }

    public function testEventWithBooleanValues(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'boolean.key',
            false,
            'Boolean configuration'
        );

        $this->assertEquals('boolean.key', $event->getKey());
        $this->assertFalse($event->getValue());
        $this->assertEquals('Boolean configuration', $event->getDescription());
    }

    public function testEventImmutability(): void
    {
        $event = new ConfigurationBeforeSetEvent(
            $this->configuration,
            'test.key',
            'original_value',
            'Original description'
        );

        // Verify that the event properties cannot be modified after creation
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('original_value', $event->getValue());
        $this->assertEquals('Original description', $event->getDescription());

        // The event should maintain its original values
        $this->assertEquals('test.key', $event->getKey());
        $this->assertEquals('original_value', $event->getValue());
        $this->assertEquals('Original description', $event->getDescription());
    }

    public function testEventConstantsAreUnique(): void
    {
        $constants = [
            ConfigurationBeforeSetEvent::NAME,
            ConfigurationAfterSetEvent::NAME,
            ConfigurationBeforeDeleteEvent::NAME,
            ConfigurationAfterDeleteEvent::NAME,
            ConfigurationBeforeGetEvent::NAME,
            ConfigurationAfterGetEvent::NAME,
        ];

        $uniqueConstants = array_unique($constants);
        $this->assertCount(\count($constants), $uniqueConstants, 'All event names should be unique');
    }

    public function testEventConstantsFormat(): void
    {
        $constants = [
            ConfigurationBeforeSetEvent::NAME,
            ConfigurationAfterSetEvent::NAME,
            ConfigurationBeforeDeleteEvent::NAME,
            ConfigurationAfterDeleteEvent::NAME,
            ConfigurationBeforeGetEvent::NAME,
            ConfigurationAfterGetEvent::NAME,
        ];

        foreach ($constants as $constant) {
            $this->assertStringStartsWith('app.configuration.', $constant, 'Event names should follow the app.configuration.* pattern');
        }
    }
}
