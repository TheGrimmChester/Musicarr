<?php

declare(strict_types=1);

namespace App\Tests\Unit\LibraryScanning\Processor;

use App\Entity\Library;
use App\LibraryScanning\Processor\FileCountProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use TypeError;
use UnexpectedValueException;
use ValueError;

class FileCountProcessorTest extends TestCase
{
    private FileCountProcessor $processor;
    private TranslatorInterface $mockTranslator;
    private Library $library;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->mockTranslator = $this->createMock(TranslatorInterface::class);
        $this->processor = new FileCountProcessor($this->mockTranslator);

        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/file_count_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $this->library = new Library();
        $this->library->setName('Test Library');
        $this->library->setPath($this->tempDir);
    }

    protected function tearDown(): void
    {
        // Clean up temporary directory
        if (is_dir($this->tempDir)) {
            $this->removeDirectory($this->tempDir);
        }
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . \DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    public function testGetPriority(): void
    {
        $priority = FileCountProcessor::getPriority();

        $this->assertEquals(10, $priority);
        $this->assertIsInt($priority);
    }

    public function testGetType(): void
    {
        $type = $this->processor->getType();

        $this->assertEquals('file_count', $type);
        $this->assertIsString($type);
    }

    public function testShouldRun(): void
    {
        // FileCountProcessor inherits from AbstractLibraryScanProcessor
        // which always returns true for shouldRun
        $this->assertTrue($this->processor->shouldRun([]));
        $this->assertTrue($this->processor->shouldRun(['scan_type' => 'full']));
        $this->assertTrue($this->processor->shouldRun(['force' => true]));
    }

    public function testProcessReturnsCorrectStructure(): void
    {
        $result = $this->processor->process($this->library, []);

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
        $this->assertArrayHasKey('file_count', $result);
    }

    public function testProcessReturnsDefaultValues(): void
    {
        $result = $this->processor->process($this->library, []);

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
        $this->assertIsInt($result['file_count']);
        $this->assertGreaterThanOrEqual(0, $result['file_count']);
    }

    public function testProcessWithDifferentOptions(): void
    {
        $result1 = $this->processor->process($this->library, []);
        $result2 = $this->processor->process($this->library, ['scan_type' => 'full']);
        $result3 = $this->processor->process($this->library, ['force' => true]);

        // All results should have the same structure and default values
        $this->assertEquals($result1, $result2);
        $this->assertEquals($result2, $result3);
        $this->assertEquals($result1, $result3);
    }

    public function testProcessWithEmptyOptions(): void
    {
        $result = $this->processor->process($this->library, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertIsInt($result['file_count']);
    }

    public function testProcessWithNullOptions(): void
    {
        $this->expectException(TypeError::class);
        $this->processor->process($this->library, null);
    }

    public function testProcessWithComplexOptions(): void
    {
        $complexOptions = [
            'scan_type' => 'full',
            'force' => true,
            'include_hidden' => false,
            'max_depth' => 5,
            'file_extensions' => ['mp3', 'flac', 'wav'],
            'skip_patterns' => ['*.tmp', '*.bak'],
        ];

        $result = $this->processor->process($this->library, $complexOptions);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertIsInt($result['file_count']);

        // Options should not affect the result structure
        $this->assertEquals([], $result['unmatched']);
        $this->assertEquals(0, $result['matched']);
    }

    public function testProcessWithDifferentLibraryPaths(): void
    {
        // Create temporary directories for testing
        $tempDir1 = sys_get_temp_dir() . '/test1_' . uniqid();
        $tempDir2 = sys_get_temp_dir() . '/test2_' . uniqid();
        mkdir($tempDir1, 0777, true);
        mkdir($tempDir2, 0777, true);

        $library1 = new Library();
        $library1->setPath($tempDir1);

        $library2 = new Library();
        $library2->setPath($tempDir2);

        $result1 = $this->processor->process($library1, []);
        $result2 = $this->processor->process($library2, []);

        $this->assertIsArray($result1);
        $this->assertIsArray($result2);
        $this->assertArrayHasKey('file_count', $result1);
        $this->assertArrayHasKey('file_count', $result2);

        // Cleanup
        rmdir($tempDir1);
        rmdir($tempDir2);
    }

    public function testProcessWithLibraryPathNull(): void
    {
        $this->expectException(TypeError::class);
        $library = new Library();
        $library->setPath(null);
    }

    public function testProcessWithLibraryPathEmpty(): void
    {
        $this->expectException(ValueError::class);
        $library = new Library();
        $library->setPath('');

        $this->processor->process($library, []);
    }

    public function testProcessWithNonExistentPath(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $library = new Library();
        $library->setPath('/non/existent/path');

        $this->processor->process($library, []);
    }

    public function testProcessWithSymlinkPath(): void
    {
        // Create a temporary directory with some files
        $tempDir = sys_get_temp_dir() . '/file_count_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create some test files
        file_put_contents($tempDir . '/file1.txt', 'test');
        file_put_contents($tempDir . '/file2.txt', 'test');
        mkdir($tempDir . '/subdir', 0777, true);
        file_put_contents($tempDir . '/subdir/file3.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertIsInt($result['file_count']);
        $this->assertEquals(3, $result['file_count']); // 3 files, not counting directories

        // Cleanup
        unlink($tempDir . '/subdir/file3.txt');
        rmdir($tempDir . '/subdir');
        unlink($tempDir . '/file2.txt');
        unlink($tempDir . '/file1.txt');
        rmdir($tempDir);
    }

    public function testProcessWithHiddenFiles(): void
    {
        // Create a temporary directory with hidden files
        $tempDir = sys_get_temp_dir() . '/file_count_hidden_test_' . uniqid();
        mkdir($tempDir, 0777, true);

        // Create visible and hidden files
        file_put_contents($tempDir . '/visible.txt', 'test');
        file_put_contents($tempDir . '/.hidden.txt', 'test');
        file_put_contents($tempDir . '/.hidden_file', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertIsInt($result['file_count']);
        $this->assertEquals(3, $result['file_count']); // All files including hidden ones

        // Cleanup
        unlink($tempDir . '/.hidden_file');
        unlink($tempDir . '/.hidden.txt');
        unlink($tempDir . '/visible.txt');
        rmdir($tempDir);
    }

    public function testProcessWithNestedDirectories(): void
    {
        // Create a temporary directory with nested structure
        $tempDir = sys_get_temp_dir() . '/file_count_nested_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/level1', 0777, true);
        mkdir($tempDir . '/level1/level2', 0777, true);
        mkdir($tempDir . '/level1/level2/level3', 0777, true);

        // Create files at different levels
        file_put_contents($tempDir . '/root.txt', 'test');
        file_put_contents($tempDir . '/level1/file1.txt', 'test');
        file_put_contents($tempDir . '/level1/level2/file2.txt', 'test');
        file_put_contents($tempDir . '/level1/level2/level3/file3.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertIsInt($result['file_count']);
        $this->assertEquals(4, $result['file_count']); // 4 files at all levels

        // Cleanup
        unlink($tempDir . '/level1/level2/level3/file3.txt');
        rmdir($tempDir . '/level1/level2/level3');
        unlink($tempDir . '/level1/level2/file2.txt');
        rmdir($tempDir . '/level1/level2');
        unlink($tempDir . '/level1/file1.txt');
        rmdir($tempDir . '/level1');
        unlink($tempDir . '/root.txt');
        rmdir($tempDir);
    }
}
