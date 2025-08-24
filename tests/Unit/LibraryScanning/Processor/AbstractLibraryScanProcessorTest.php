<?php

declare(strict_types=1);

namespace App\Tests\Unit\LibraryScanning\Processor;

use App\Entity\Library;
use App\LibraryScanning\Processor\AbstractLibraryScanProcessor;
use PHPUnit\Framework\TestCase;

class AbstractLibraryScanProcessorTest extends TestCase
{
    private TestAbstractProcessor $processor;
    private Library $library;

    protected function setUp(): void
    {
        $this->processor = new TestAbstractProcessor();

        $this->library = new Library();
        $this->library->setName('Test Library');
        $this->library->setPath('/test/path');
    }

    public function testShouldRunDefaultImplementation(): void
    {
        // AbstractLibraryScanProcessor should always return true for shouldRun
        $this->assertTrue($this->processor->shouldRun([]));
        $this->assertTrue($this->processor->shouldRun(['scan_type' => 'full']));
        $this->assertTrue($this->processor->shouldRun(['force' => true]));
        $this->assertTrue($this->processor->shouldRun(['clean_empty_dirs' => false]));
    }

    public function testMergeResultsWithEmptyArray(): void
    {
        $result = $this->processor->testMergeResults([]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('unmatched', $result);
        $this->assertArrayHasKey('matched', $result);
        $this->assertArrayHasKey('path_updates', $result);
        $this->assertArrayHasKey('removed_files', $result);
        $this->assertArrayHasKey('updated_files', $result);
        $this->assertArrayHasKey('track_files_created', $result);
        $this->assertArrayHasKey('analysis_sent', $result);
        $this->assertArrayHasKey('sync_count', $result);
        $this->assertArrayHasKey('fix_count', $result);
        $this->assertArrayHasKey('album_updates', $result);

        // All values should be default (0 or empty array)
        $this->assertEquals([], $result['unmatched']);
        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(0, $result['path_updates']);
        $this->assertEquals(0, $result['removed_files']);
        $this->assertEquals(0, $result['updated_files']);
        $this->assertEquals(0, $result['track_files_created']);
        $this->assertEquals(0, $result['analysis_sent']);
        $this->assertEquals(0, $result['sync_count']);
        $this->assertEquals(0, $result['fix_count']);
        $this->assertEquals(0, $result['album_updates']);
    }

    public function testMergeResultsWithSingleResult(): void
    {
        $singleResult = [
            'unmatched' => ['file1.mp3', 'file2.mp3'],
            'matched' => 5,
            'path_updates' => 2,
            'removed_files' => 1,
            'updated_files' => 3,
            'track_files_created' => 4,
            'analysis_sent' => 6,
            'sync_count' => 7,
            'fix_count' => 8,
            'album_updates' => 9,
        ];

        $result = $this->processor->testMergeResults([$singleResult]);

        $this->assertEquals(['file1.mp3', 'file2.mp3'], $result['unmatched']);
        $this->assertEquals(5, $result['matched']);
        $this->assertEquals(2, $result['path_updates']);
        $this->assertEquals(1, $result['removed_files']);
        $this->assertEquals(3, $result['updated_files']);
        $this->assertEquals(4, $result['track_files_created']);
        $this->assertEquals(6, $result['analysis_sent']);
        $this->assertEquals(7, $result['sync_count']);
        $this->assertEquals(8, $result['fix_count']);
        $this->assertEquals(9, $result['album_updates']);
    }

    public function testMergeResultsWithMultipleResults(): void
    {
        $results = [
            [
                'unmatched' => ['file1.mp3', 'file2.mp3'],
                'matched' => 5,
                'path_updates' => 2,
                'removed_files' => 1,
                'updated_files' => 3,
                'track_files_created' => 4,
                'analysis_sent' => 6,
                'sync_count' => 7,
                'fix_count' => 8,
                'album_updates' => 9,
            ],
            [
                'unmatched' => ['file3.mp3', 'file4.mp3'],
                'matched' => 10,
                'path_updates' => 5,
                'removed_files' => 2,
                'updated_files' => 7,
                'track_files_created' => 8,
                'analysis_sent' => 12,
                'sync_count' => 14,
                'fix_count' => 16,
                'album_updates' => 18,
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        // Arrays should be merged
        $this->assertEquals(['file1.mp3', 'file2.mp3', 'file3.mp3', 'file4.mp3'], $result['unmatched']);

        // Numbers should be summed
        $this->assertEquals(15, $result['matched']); // 5 + 10
        $this->assertEquals(7, $result['path_updates']); // 2 + 5
        $this->assertEquals(3, $result['removed_files']); // 1 + 2
        $this->assertEquals(10, $result['updated_files']); // 3 + 7
        $this->assertEquals(12, $result['track_files_created']); // 4 + 8
        $this->assertEquals(18, $result['analysis_sent']); // 6 + 12
        $this->assertEquals(21, $result['sync_count']); // 7 + 14
        $this->assertEquals(24, $result['fix_count']); // 8 + 16
        $this->assertEquals(27, $result['album_updates']); // 9 + 18
    }

    public function testMergeResultsWithMissingKeys(): void
    {
        $results = [
            [
                'matched' => 5,
                'path_updates' => 2,
                // Missing other keys
            ],
            [
                'unmatched' => ['file1.mp3'],
                'file_count' => 10,
                // Missing other keys
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        // Should handle missing keys gracefully
        $this->assertEquals(['file1.mp3'], $result['unmatched']);
        $this->assertEquals(5, $result['matched']);
        $this->assertEquals(2, $result['path_updates']);
        $this->assertEquals(0, $result['removed_files']); // Default value
        $this->assertEquals(0, $result['updated_files']); // Default value
        $this->assertEquals(0, $result['track_files_created']); // Default value
        $this->assertEquals(0, $result['analysis_sent']); // Default value
        $this->assertEquals(0, $result['sync_count']); // Default value
        $this->assertEquals(0, $result['fix_count']); // Default value
        $this->assertEquals(0, $result['album_updates']); // Default value
    }

    public function testMergeResultsWithNullValues(): void
    {
        $results = [
            [
                'unmatched' => null,
                'matched' => null,
                'path_updates' => null,
                'removed_files' => null,
                'updated_files' => null,
                'track_files_created' => null,
                'analysis_sent' => null,
                'sync_count' => null,
                'fix_count' => null,
                'album_updates' => null,
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        // Should handle null values gracefully
        $this->assertEquals([], $result['unmatched']);
        $this->assertEquals(0, $result['matched']);
        $this->assertEquals(0, $result['path_updates']);
        $this->assertEquals(0, $result['removed_files']);
        $this->assertEquals(0, $result['updated_files']);
        $this->assertEquals(0, $result['track_files_created']);
        $this->assertEquals(0, $result['analysis_sent']);
        $this->assertEquals(0, $result['sync_count']);
        $this->assertEquals(0, $result['fix_count']);
        $this->assertEquals(0, $result['album_updates']);
    }

    public function testMergeResultsWithMixedDataTypes(): void
    {
        $results = [
            [
                'unmatched' => ['file1.mp3'],
                'matched' => '5', // String instead of int
                'path_updates' => 2.5, // Float instead of int
                'removed_files' => true, // Boolean instead of int
                'updated_files' => '3', // String instead of int
                'track_files_created' => 4,
                'analysis_sent' => 6,
                'sync_count' => 7,
                'fix_count' => 8,
                'album_updates' => 9,
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        // Should handle mixed data types gracefully
        $this->assertEquals(['file1.mp3'], $result['unmatched']);
        $this->assertEquals(5, $result['matched']); // String '5' should be treated as 5
        $this->assertEquals(2.5, $result['path_updates']); // Float 2.5 should be preserved as float
        $this->assertEquals(1, $result['removed_files']); // Boolean true should be treated as 1
        $this->assertEquals(3, $result['updated_files']); // String '3' should be treated as 3
        $this->assertEquals(4, $result['track_files_created']);
        $this->assertEquals(6, $result['analysis_sent']);
        $this->assertEquals(7, $result['sync_count']);
        $this->assertEquals(8, $result['fix_count']);
        $this->assertEquals(9, $result['album_updates']);
    }

    public function testMergeResultsWithEmptyUnmatchedArrays(): void
    {
        $results = [
            [
                'unmatched' => [],
                'matched' => 5,
            ],
            [
                'unmatched' => [],
                'matched' => 10,
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        $this->assertEquals([], $result['unmatched']);
        $this->assertEquals(15, $result['matched']);
    }

    public function testMergeResultsWithUnmatchedArraysContainingNulls(): void
    {
        $results = [
            [
                'unmatched' => ['file1.mp3', null, 'file2.mp3'],
                'matched' => 5,
            ],
            [
                'unmatched' => [null, 'file3.mp3'],
                'matched' => 10,
            ],
        ];

        $result = $this->processor->testMergeResults($results);

        $this->assertEquals(['file1.mp3', null, 'file2.mp3', null, 'file3.mp3'], $result['unmatched']);
        $this->assertEquals(15, $result['matched']);
    }
}

/**
 * Test implementation of AbstractLibraryScanProcessor for testing purposes.
 */
class TestAbstractProcessor extends AbstractLibraryScanProcessor
{
    public static function getPriority(): int
    {
        return 0;
    }

    public function getType(): string
    {
        return 'test_processor';
    }

    public function process(Library $library, array $options): array
    {
        return [];
    }

    /**
     * Expose the protected mergeResults method for testing.
     */
    public function testMergeResults(array $results): array
    {
        return $this->mergeResults($results);
    }
}
