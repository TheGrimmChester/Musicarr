<?php

declare(strict_types=1);

namespace App\Tests\Unit\Task\Processor;

use App\Configuration\Domain\AssociationConfigurationDomain;
use App\Entity\Task;
use App\Repository\UnmatchedTrackRepository;
use App\Task\Processor\AutoAssociateTracksTaskProcessor;
use App\Task\Processor\AutoAssociateTrackTaskProcessor;
use App\Task\Processor\TaskProcessorResult;
use App\Task\TaskFactory;
use App\UnmatchedTrackAssociation\UnmatchedTrackAssociationChain;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AutoAssociateTaskProcessorTest extends TestCase
{
    public function testAutoAssociateTrackTaskProcessorFailsWhenAutoAssociationDisabled(): void
    {
        // Mock dependencies
        $associationDomain = $this->createMock(AssociationConfigurationDomain::class);
        $unmatchedTrackRepository = $this->createMock(UnmatchedTrackRepository::class);
        $unmatchedTrackAssociationChain = $this->createMock(UnmatchedTrackAssociationChain::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Configure mock to return false for auto association enabled
        $associationDomain->expects($this->once())
            ->method('isAutoAssociationEnabled')
            ->willReturn(false);

        // Create processor
        $processor = new AutoAssociateTrackTaskProcessor(
            $associationDomain,
            $unmatchedTrackRepository,
            $unmatchedTrackAssociationChain,
            $logger
        );

        // Create a mock task
        $task = $this->createMock(Task::class);
        $task->method('getEntityId')->willReturn(123);
        $task->method('getEntityName')->willReturn('Test Track');
        $task->method('getMetadata')->willReturn([]);

        // Process the task
        $result = $processor->process($task);

        // Assert that the task failed with the expected message
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Auto association is disabled in configuration', $result->getErrorMessage());
    }

    public function testAutoAssociateTracksTaskProcessorFailsWhenAutoAssociationDisabled(): void
    {
        // Mock dependencies
        $associationDomain = $this->createMock(AssociationConfigurationDomain::class);
        $unmatchedTrackRepository = $this->createMock(UnmatchedTrackRepository::class);
        $taskFactory = $this->createMock(TaskFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Configure mock to return false for auto association enabled
        $associationDomain->expects($this->once())
            ->method('isAutoAssociationEnabled')
            ->willReturn(false);

        // Create processor
        $processor = new AutoAssociateTracksTaskProcessor(
            $associationDomain,
            $unmatchedTrackRepository,
            $taskFactory,
            $logger
        );

        // Create a mock task
        $task = $this->createMock(Task::class);
        $task->method('getEntityId')->willReturn(456);
        $task->method('getEntityName')->willReturn('Test Library');
        $task->method('getMetadata')->willReturn([]);

        // Process the task
        $result = $processor->process($task);

        // Assert that the task failed with the expected message
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertEquals('Auto association is disabled in configuration', $result->getErrorMessage());
    }

    public function testAutoAssociateTrackTaskProcessorProceedsWhenAutoAssociationEnabled(): void
    {
        // Mock dependencies
        $associationDomain = $this->createMock(AssociationConfigurationDomain::class);
        $unmatchedTrackRepository = $this->createMock(UnmatchedTrackRepository::class);
        $unmatchedTrackAssociationChain = $this->createMock(UnmatchedTrackAssociationChain::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Configure mock to return true for auto association enabled
        $associationDomain->expects($this->once())
            ->method('isAutoAssociationEnabled')
            ->willReturn(true);

        // Configure repository to return null (track not found)
        $unmatchedTrackRepository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn(null);

        // Create processor
        $processor = new AutoAssociateTrackTaskProcessor(
            $associationDomain,
            $unmatchedTrackRepository,
            $unmatchedTrackAssociationChain,
            $logger
        );

        // Create a mock task
        $task = $this->createMock(Task::class);
        $task->method('getEntityId')->willReturn(123);
        $task->method('getEntityName')->willReturn('Test Track');
        $task->method('getMetadata')->willReturn([]);

        // Process the task
        $result = $processor->process($task);

        // Assert that the task failed because track was not found (not because auto association is disabled)
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertFalse($result->isSuccess());
        $this->assertStringContainsString('not found', $result->getErrorMessage());
        $this->assertStringNotContainsString('disabled in configuration', $result->getErrorMessage());
    }

    public function testAutoAssociateTracksTaskProcessorProceedsWhenAutoAssociationEnabled(): void
    {
        // Mock dependencies
        $associationDomain = $this->createMock(AssociationConfigurationDomain::class);
        $unmatchedTrackRepository = $this->createMock(UnmatchedTrackRepository::class);
        $taskFactory = $this->createMock(TaskFactory::class);
        $logger = $this->createMock(LoggerInterface::class);

        // Configure mock to return true for auto association enabled
        $associationDomain->expects($this->once())
            ->method('isAutoAssociationEnabled')
            ->willReturn(true);

        // Configure repository to return empty array (no unmatched tracks)
        $unmatchedTrackRepository->expects($this->once())
            ->method('findUnmatchedByLibrary')
            ->with(456)
            ->willReturn([]);

        // Create processor
        $processor = new AutoAssociateTracksTaskProcessor(
            $associationDomain,
            $unmatchedTrackRepository,
            $taskFactory,
            $logger
        );

        // Create a mock task
        $task = $this->createMock(Task::class);
        $task->method('getEntityId')->willReturn(456);
        $task->method('getEntityName')->willReturn('Test Library');
        $task->method('getMetadata')->willReturn([]);
        $task->method('getId')->willReturn(789);

        // Process the task
        $result = $processor->process($task);

        // Assert that the task succeeded (no unmatched tracks found)
        $this->assertInstanceOf(TaskProcessorResult::class, $result);
        $this->assertTrue($result->isSuccess());
        $this->assertStringContainsString('No unmatched tracks found', $result->getMessage());
        $this->assertStringNotContainsString('disabled in configuration', $result->getMessage());
    }
}
