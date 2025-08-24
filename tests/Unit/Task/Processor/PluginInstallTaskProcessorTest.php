<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Processor;

use App\Entity\Task;
use App\Plugin\PluginManager;
use App\Service\PluginStatusManager;
use App\Task\Processor\PluginInstallTaskProcessor;
use App\Task\Processor\TaskProcessorResult;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class PluginInstallTaskProcessorTest extends TestCase
{
    private PluginInstallTaskProcessor $processor;
    private PluginManager $pluginManager;
    private PluginStatusManager $pluginStatusManager;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->pluginManager = $this->createMock(PluginManager::class);
        $this->pluginStatusManager = $this->createMock(PluginStatusManager::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->processor = new PluginInstallTaskProcessor(
            $this->pluginManager,
            $this->pluginStatusManager,
            $this->logger
        );
    }

    public function testProcessSuccessfullyInstallsPlugin(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata(['plugin_name' => 'test-plugin']);

        $pluginData = [
            'bundle_class' => 'TestPlugin\TestPluginBundle',
            'version' => '1.0.0',
            'author' => 'Test Author',
        ];

        $this->pluginManager
            ->expects($this->once())
            ->method('getPlugin')
            ->with('test-plugin')
            ->willReturn($pluginData);

        $this->pluginStatusManager
            ->expects($this->once())
            ->method('isPluginEnabled')
            ->with('TestPlugin\TestPluginBundle')
            ->willReturn(false);

        $this->pluginStatusManager
            ->expects($this->once())
            ->method('installPlugin')
            ->with('TestPlugin\TestPluginBundle')
            ->willReturn(true);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals("Successfully installed plugin 'test-plugin'", $result->getMessage());

        $metadata = $result->getMetadata();
        $this->assertEquals('test-plugin', $metadata['pluginName']);
        $this->assertEquals('installed', $metadata['status']);
        $this->assertEquals('1.0.0', $metadata['version']);
        $this->assertEquals('Test Author', $metadata['author']);
        $this->assertEquals('TestPlugin\TestPluginBundle', $metadata['bundleClass']);
    }

    public function testProcessFailsWhenNoPluginNameProvided(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata([]);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('No plugin name provided', $result->getErrorMessage());
    }

    public function testProcessFailsWhenPluginNotFound(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata(['plugin_name' => 'non-existent-plugin']);

        $this->pluginManager
            ->expects($this->once())
            ->method('getPlugin')
            ->with('non-existent-plugin')
            ->willReturn(null);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Plugin 'non-existent-plugin' not found", $result->getErrorMessage());
    }

    public function testProcessFailsWhenPluginHasNoBundleClass(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata(['plugin_name' => 'test-plugin']);

        $pluginData = [
            'version' => '1.0.0',
            'author' => 'Test Author',
        ];

        $this->pluginManager
            ->expects($this->once())
            ->method('getPlugin')
            ->with('test-plugin')
            ->willReturn($pluginData);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Plugin 'test-plugin' has no bundle class defined", $result->getErrorMessage());
    }

    public function testProcessReturnsAlreadyInstalledWhenPluginIsEnabled(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata(['plugin_name' => 'test-plugin']);

        $pluginData = [
            'bundle_class' => 'TestPlugin\TestPluginBundle',
            'version' => '1.0.0',
            'author' => 'Test Author',
        ];

        $this->pluginManager
            ->expects($this->once())
            ->method('getPlugin')
            ->with('test-plugin')
            ->willReturn($pluginData);

        $this->pluginStatusManager
            ->expects($this->once())
            ->method('isPluginEnabled')
            ->with('TestPlugin\TestPluginBundle')
            ->willReturn(true);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertEquals("Plugin 'test-plugin' is already installed", $result->getMessage());

        $metadata = $result->getMetadata();
        $this->assertEquals('test-plugin', $metadata['pluginName']);
        $this->assertEquals('already_installed', $metadata['status']);
    }

    public function testProcessFailsWhenInstallPluginReturnsFalse(): void
    {
        // Arrange
        $task = new Task();
        $task->setType(Task::TYPE_PLUGIN_INSTALL);
        $task->setMetadata(['plugin_name' => 'test-plugin']);

        $pluginData = [
            'bundle_class' => 'TestPlugin\TestPluginBundle',
            'version' => '1.0.0',
            'author' => 'Test Author',
        ];

        $this->pluginManager
            ->expects($this->once())
            ->method('getPlugin')
            ->with('test-plugin')
            ->willReturn($pluginData);

        $this->pluginStatusManager
            ->expects($this->once())
            ->method('isPluginEnabled')
            ->with('TestPlugin\TestPluginBundle')
            ->willReturn(false);

        $this->pluginStatusManager
            ->expects($this->once())
            ->method('installPlugin')
            ->with('TestPlugin\TestPluginBundle')
            ->willReturn(false);

        // Act
        $result = $this->processor->process($task);

        // Assert
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals("Failed to install plugin 'test-plugin'", $result->getErrorMessage());
    }

    public function testGetSupportedTaskTypes(): void
    {
        // Act
        $supportedTypes = $this->processor->getSupportedTaskTypes();

        // Assert
        $this->assertEquals([Task::TYPE_PLUGIN_INSTALL], $supportedTypes);
    }

    public function testSupports(): void
    {
        // Arrange
        $supportedTask = new Task();
        $supportedTask->setType(Task::TYPE_PLUGIN_INSTALL);

        $unsupportedTask = new Task();
        $unsupportedTask->setType(Task::TYPE_ADD_ARTIST);

        // Act & Assert
        $this->assertTrue($this->processor->supports($supportedTask));
        $this->assertFalse($this->processor->supports($unsupportedTask));
    }
}
