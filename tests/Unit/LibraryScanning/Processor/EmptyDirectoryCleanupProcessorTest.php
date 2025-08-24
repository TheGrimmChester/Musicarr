<?php

declare(strict_types=1);

namespace App\Tests\Unit\LibraryScanning\Processor;

use App\Entity\Library;
use App\LibraryScanning\Processor\EmptyDirectoryCleanupProcessor;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;
use TypeError;
use UnexpectedValueException;
use ValueError;

class EmptyDirectoryCleanupProcessorTest extends TestCase
{
    private EmptyDirectoryCleanupProcessor $processor;
    private TranslatorInterface $mockTranslator;
    private Library $library;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->mockTranslator = $this->createMock(TranslatorInterface::class);
        $this->processor = new EmptyDirectoryCleanupProcessor($this->mockTranslator);

        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/empty_dir_test_' . uniqid();
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
        $priority = EmptyDirectoryCleanupProcessor::getPriority();

        $this->assertEquals(50, $priority);
        $this->assertIsInt($priority);
    }

    public function testGetType(): void
    {
        $type = $this->processor->getType();

        $this->assertEquals('empty_directory_cleanup', $type);
        $this->assertIsString($type);
    }

    public function testShouldRunWithCleanEmptyDirsOption(): void
    {
        $this->assertTrue($this->processor->shouldRun(['clean_empty_dirs' => true]));
        $this->assertFalse($this->processor->shouldRun(['clean_empty_dirs' => false]));
        $this->assertFalse($this->processor->shouldRun([]));
        $this->assertFalse($this->processor->shouldRun(['other_option' => true]));
    }

    public function testProcessReturnsCorrectStructure(): void
    {
        $result = $this->processor->process($this->library, ['clean_empty_dirs' => true]);

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
        $result = $this->processor->process($this->library, ['clean_empty_dirs' => true]);

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
        $this->assertEquals(0, $result['file_count']);
    }

    public function testProcessWithDryRunOption(): void
    {
        $result = $this->processor->process($this->library, [
            'clean_empty_dirs' => true,
            'dry_run' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testProcessWithCleanEmptyDirsFalse(): void
    {
        $result = $this->processor->process($this->library, ['clean_empty_dirs' => false]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testProcessWithEmptyOptions(): void
    {
        $result = $this->processor->process($this->library, []);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);
    }

    public function testProcessWithNullOptions(): void
    {
        $this->expectException(TypeError::class);
        $this->processor->process($this->library, null);
    }

    public function testProcessWithComplexOptions(): void
    {
        $complexOptions = [
            'clean_empty_dirs' => true,
            'dry_run' => false,
            'scan_type' => 'full',
            'force' => true,
            'include_hidden' => false,
            'max_depth' => 5,
        ];

        $result = $this->processor->process($this->library, $complexOptions);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

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

        $result1 = $this->processor->process($library1, ['clean_empty_dirs' => true]);
        $result2 = $this->processor->process($library2, ['clean_empty_dirs' => true]);

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

        $this->processor->process($library, ['clean_empty_dirs' => true]);
    }

    public function testProcessWithNonExistentPath(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $library = new Library();
        $library->setPath('/non/existent/path');

        $this->processor->process($library, ['clean_empty_dirs' => true]);
    }

    public function testProcessWithEmptyDirectories(): void
    {
        // Create a temporary directory structure with empty directories
        $tempDir = sys_get_temp_dir() . '/empty_dir_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/empty1', 0777, true);
        mkdir($tempDir . '/empty2', 0777, true);
        mkdir($tempDir . '/empty3', 0777, true);

        // Create one file to make the root directory non-empty
        file_put_contents($tempDir . '/file.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, ['clean_empty_dirs' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

        // Check that empty directories were removed
        $this->assertDirectoryDoesNotExist($tempDir . '/empty1');
        $this->assertDirectoryDoesNotExist($tempDir . '/empty2');
        $this->assertDirectoryDoesNotExist($tempDir . '/empty3');
        $this->assertDirectoryExists($tempDir); // Root should still exist
        $this->assertFileExists($tempDir . '/file.txt'); // File should still exist

        // Cleanup
        unlink($tempDir . '/file.txt');
        rmdir($tempDir);
    }

    public function testProcessWithDryRunEmptyDirectories(): void
    {
        // Create a temporary directory structure with empty directories
        $tempDir = sys_get_temp_dir() . '/empty_dir_dry_run_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/empty1', 0777, true);
        mkdir($tempDir . '/empty2', 0777, true);

        // Create one file to make the root directory non-empty
        file_put_contents($tempDir . '/file.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, [
            'clean_empty_dirs' => true,
            'dry_run' => true,
        ]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

        // Check that empty directories were NOT removed in dry run
        $this->assertDirectoryExists($tempDir . '/empty1');
        $this->assertDirectoryExists($tempDir . '/empty2');
        $this->assertDirectoryExists($tempDir);
        $this->assertFileExists($tempDir . '/file.txt');

        // Cleanup
        rmdir($tempDir . '/empty1');
        rmdir($tempDir . '/empty2');
        unlink($tempDir . '/file.txt');
        rmdir($tempDir);
    }

    public function testProcessWithMixedDirectoryStructure(): void
    {
        // Create a temporary directory structure with mixed content
        $tempDir = sys_get_temp_dir() . '/mixed_dir_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/empty1', 0777, true);
        mkdir($tempDir . '/non_empty', 0777, true);
        mkdir($tempDir . '/empty2', 0777, true);

        // Create files in non-empty directory
        file_put_contents($tempDir . '/non_empty/file1.txt', 'test');
        file_put_contents($tempDir . '/non_empty/file2.txt', 'test');

        // Create one file in root
        file_put_contents($tempDir . '/root_file.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, ['clean_empty_dirs' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

        // Check that only empty directories were removed
        $this->assertDirectoryDoesNotExist($tempDir . '/empty1');
        $this->assertDirectoryDoesNotExist($tempDir . '/empty2');
        $this->assertDirectoryExists($tempDir . '/non_empty'); // Should still exist
        $this->assertDirectoryExists($tempDir); // Root should still exist
        $this->assertFileExists($tempDir . '/root_file.txt'); // Root file should still exist
        $this->assertFileExists($tempDir . '/non_empty/file1.txt'); // Files should still exist
        $this->assertFileExists($tempDir . '/non_empty/file2.txt');

        // Cleanup
        unlink($tempDir . '/non_empty/file2.txt');
        unlink($tempDir . '/non_empty/file1.txt');
        rmdir($tempDir . '/non_empty');
        unlink($tempDir . '/root_file.txt');
        rmdir($tempDir);
    }

    public function testProcessWithNestedEmptyDirectories(): void
    {
        // Create a temporary directory structure with nested empty directories
        $tempDir = sys_get_temp_dir() . '/nested_empty_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/level1', 0777, true);
        mkdir($tempDir . '/level1/level2', 0777, true);
        mkdir($tempDir . '/level1/level2/level3', 0777, true);

        // Create one file in root
        file_put_contents($tempDir . '/root_file.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, ['clean_empty_dirs' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

        // Check that all nested empty directories were removed
        $this->assertDirectoryDoesNotExist($tempDir . '/level1/level2/level3');
        $this->assertDirectoryDoesNotExist($tempDir . '/level1/level2');
        $this->assertDirectoryDoesNotExist($tempDir . '/level1');
        $this->assertDirectoryExists($tempDir); // Root should still exist
        $this->assertFileExists($tempDir . '/root_file.txt'); // Root file should still exist

        // Cleanup
        unlink($tempDir . '/root_file.txt');
        rmdir($tempDir);
    }

    public function testProcessWithHiddenEmptyDirectories(): void
    {
        // Create a temporary directory structure with hidden empty directories
        $tempDir = sys_get_temp_dir() . '/hidden_empty_test_' . uniqid();
        mkdir($tempDir, 0777, true);
        mkdir($tempDir . '/.hidden_empty', 0777, true);
        mkdir($tempDir . '/visible_empty', 0777, true);

        // Create one file in root
        file_put_contents($tempDir . '/root_file.txt', 'test');

        $library = new Library();
        $library->setPath($tempDir);

        $result = $this->processor->process($library, ['clean_empty_dirs' => true]);

        $this->assertIsArray($result);
        $this->assertArrayHasKey('file_count', $result);
        $this->assertEquals(0, $result['file_count']);

        // Check that both hidden and visible empty directories were removed
        $this->assertDirectoryDoesNotExist($tempDir . '/.hidden_empty');
        $this->assertDirectoryDoesNotExist($tempDir . '/visible_empty');
        $this->assertDirectoryExists($tempDir); // Root should still exist
        $this->assertFileExists($tempDir . '/root_file.txt'); // Root file should still exist

        // Cleanup
        unlink($tempDir . '/root_file.txt');
        rmdir($tempDir);
    }
}
