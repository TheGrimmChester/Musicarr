<?php

declare(strict_types=1);

namespace App\Tests\Integration\LibraryScanning;

use App\Entity\Library;
use App\LibraryScanning\LibraryScanChain;
use App\LibraryScanning\Processor\LibraryScanProcessorInterface;
use PHPUnit\Framework\TestCase;

class LibraryScanChainTest extends TestCase
{
    private LibraryScanChain $chain;
    private array $mockProcessors;
    private Library $library;

    protected function setUp(): void
    {
        $this->library = new Library();
        $this->library->setName('Test Library');
        $this->library->setPath('/test/path');

        // Create mock processors
        $this->mockProcessors = [
            $this->createMockProcessor('processor1', 10, true, ['matched' => 5, 'file_count' => 10]),
            $this->createMockProcessor('processor2', 20, true, ['matched' => 3, 'file_count' => 8]),
            $this->createMockProcessor('processor3', 15, false, []), // Should not run
            $this->createMockProcessor('processor4', 25, true, ['unmatched' => ['file1.mp3', 'file2.mp3']]),
        ];

        $this->chain = new LibraryScanChain($this->mockProcessors);
    }

    private function createMockProcessor(string $type, int $priority, bool $shouldRun, array $result): LibraryScanProcessorInterface
    {
        $processor = $this->createMock(LibraryScanProcessorInterface::class);

        $processor->method('getType')->willReturn($type);
        $processor->method('getPriority')->willReturn($priority);
        $processor->method('shouldRun')->willReturn($shouldRun);
        $processor->method('process')->willReturn($result);

        return $processor;
    }

    public function testExecuteChain(): void
    {
        $options = ['scan_type' => 'full'];

        $result = $this->chain->executeChain($this->library, $options);

        // Should only process processors that should run (processor1, processor2, processor4)
        $this->assertArrayHasKey('matched', $result);
        $this->assertEquals(8, $result['matched']); // 5 + 3
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(18, $result['file_count']); // 10 + 8
        $this->assertArrayHasKey('unmatched', $result);
        $this->assertEquals(['file1.mp3', 'file2.mp3'], $result['unmatched']);
    }

    public function testExecuteChainWithTypes(): void
    {
        $options = ['scan_type' => 'full'];
        $types = ['processor1', 'processor4'];

        $result = $this->chain->executeChainWithTypes($this->library, $options, $types);

        // Should only process specified types that should run
        $this->assertArrayHasKey('matched', $result);
        $this->assertEquals(5, $result['matched']); // Only processor1
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(10, $result['file_count']); // Only processor1
        $this->assertArrayHasKey('unmatched', $result);
        $this->assertEquals(['file1.mp3', 'file2.mp3'], $result['unmatched']); // From processor4
    }

    public function testExecuteChainWithTypesNoMatchingTypes(): void
    {
        $options = ['scan_type' => 'full'];
        $types = ['nonexistent'];

        $result = $this->chain->executeChainWithTypes($this->library, $options, $types);

        // Should return default merged structure with zero values
        $this->assertArrayHasKey('matched', $result);
        $this->assertEquals(0, $result['matched']);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testGetAvailableTypes(): void
    {
        $types = $this->chain->getAvailableTypes();

        $this->assertCount(4, $types);
        $this->assertContains('processor1', $types);
        $this->assertContains('processor2', $types);
        $this->assertContains('processor3', $types);
        $this->assertContains('processor4', $types);
    }

    public function testGetProcessorByType(): void
    {
        $processor = $this->chain->getProcessorByType('processor1');

        $this->assertNotNull($processor);
        $this->assertEquals('processor1', $processor->getType());
    }

    public function testGetProcessorByTypeNotFound(): void
    {
        $processor = $this->chain->getProcessorByType('nonexistent');

        $this->assertNull($processor);
    }

    public function testExecuteChainWithEmptyProcessors(): void
    {
        $emptyChain = new LibraryScanChain([]);

        $result = $emptyChain->executeChain($this->library, []);

        // Should return default merged structure
        $this->assertArrayHasKey('matched', $result);
        $this->assertEquals(0, $result['matched']);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testExecuteChainWithAllProcessorsSkipped(): void
    {
        // Create processors that all return false for shouldRun
        $skippedProcessors = [
            $this->createMockProcessor('skipped1', 10, false, []),
            $this->createMockProcessor('skipped2', 20, false, []),
        ];

        $skippedChain = new LibraryScanChain($skippedProcessors);

        $result = $skippedChain->executeChain($this->library, []);

        // Should return default merged structure with zero values
        $this->assertArrayHasKey('matched', $result);
        $this->assertEquals(0, $result['matched']);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testExecuteChainWithComplexResults(): void
    {
        $complexProcessors = [
            $this->createMockProcessor('complex1', 10, true, [
                'matched' => 5,
                'path_updates' => 2,
                'removed_files' => 1,
                'updated_files' => 3,
                'track_files_created' => 4,
                'analysis_sent' => 6,
                'sync_count' => 7,
                'fix_count' => 8,
                'track_status_fixes' => 9,
                'auto_associations' => 10,
                'album_updates' => 11,
                'file_count' => 12,
                'unmatched' => ['file1.mp3'],
            ]),
            $this->createMockProcessor('complex2', 20, true, [
                'matched' => 3,
                'path_updates' => 1,
                'removed_files' => 0,
                'updated_files' => 2,
                'track_files_created' => 1,
                'analysis_sent' => 4,
                'sync_count' => 5,
                'fix_count' => 6,
                'track_status_fixes' => 7,
                'auto_associations' => 8,
                'album_updates' => 9,
                'file_count' => 10,
                'unmatched' => ['file2.mp3', 'file3.mp3'],
            ]),
        ];

        $complexChain = new LibraryScanChain($complexProcessors);

        $result = $complexChain->executeChain($this->library, []);

        // Test all merged values
        $this->assertEquals(8, $result['matched']); // 5 + 3
        $this->assertEquals(3, $result['path_updates']); // 2 + 1
        $this->assertEquals(1, $result['removed_files']); // 1 + 0
        $this->assertEquals(5, $result['updated_files']); // 3 + 2
        $this->assertEquals(5, $result['track_files_created']); // 4 + 1
        $this->assertEquals(10, $result['analysis_sent']); // 6 + 4
        $this->assertEquals(12, $result['sync_count']); // 7 + 5
        $this->assertEquals(14, $result['fix_count']); // 8 + 6
        $this->assertEquals(16, $result['track_status_fixes']); // 9 + 7
        $this->assertEquals(18, $result['auto_associations']); // 10 + 8
        $this->assertEquals(20, $result['album_updates']); // 11 + 9
        $this->assertEquals(22, $result['file_count']); // 12 + 10
        $this->assertEquals(['file1.mp3', 'file2.mp3', 'file3.mp3'], $result['unmatched']);
    }

    public function testExecuteChainWithMissingResultKeys(): void
    {
        $incompleteProcessors = [
            $this->createMockProcessor('incomplete1', 10, true, [
                'matched' => 5,
                // Missing other keys
            ]),
            $this->createMockProcessor('incomplete2', 20, true, [
                'file_count' => 10,
                // Missing other keys
            ]),
        ];

        $incompleteChain = new LibraryScanChain($incompleteProcessors);

        $result = $incompleteChain->executeChain($this->library, []);

        // Should handle missing keys gracefully with default values
        $this->assertEquals(5, $result['matched']);
        $this->assertEquals(10, $result['file_count']);
        $this->assertEquals(0, $result['path_updates']);
        $this->assertEquals(0, $result['removed_files']);
        $this->assertEquals(0, $result['updated_files']);
        $this->assertEquals(0, $result['track_files_created']);
        $this->assertEquals(0, $result['analysis_sent']);
        $this->assertEquals(0, $result['sync_count']);
        $this->assertEquals(0, $result['fix_count']);
        $this->assertEquals(0, $result['track_status_fixes']);
        $this->assertEquals(0, $result['auto_associations']);
        $this->assertEquals(0, $result['album_updates']);
        $this->assertEquals([], $result['unmatched']);
    }

    public function testExecuteChainWithNullValues(): void
    {
        $nullProcessors = [
            $this->createMockProcessor('null1', 10, true, [
                'matched' => null,
                'file_count' => null,
            ]),
        ];

        $nullChain = new LibraryScanChain($nullProcessors);

        $result = $nullChain->executeChain($this->library, []);

        // Should handle null values gracefully
        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(0, $result['file_count']);
    }
}
