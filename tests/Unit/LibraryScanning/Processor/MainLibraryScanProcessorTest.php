<?php

declare(strict_types=1);

namespace App\Tests\Unit\LibraryScanning\Processor;

use App\Entity\Library;
use App\LibraryScanning\Processor\MainLibraryScanProcessor;
use App\Scanner\LibraryScanner;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use TypeError;

class MainLibraryScanProcessorTest extends TestCase
{
    private MainLibraryScanProcessor $processor;
    private LibraryScanner $mockLibraryScanner;
    private Library $library;

    protected function setUp(): void
    {
        $this->mockLibraryScanner = $this->createMock(LibraryScanner::class);
        $this->processor = new MainLibraryScanProcessor($this->mockLibraryScanner);

        $this->library = new Library();
        $this->library->setName('Test Library');
        $this->library->setPath('/test/path');
    }

    public function testGetPriority(): void
    {
        $priority = MainLibraryScanProcessor::getPriority();

        $this->assertEquals(100, $priority);
        $this->assertIsInt($priority);
    }

    public function testGetType(): void
    {
        $type = $this->processor->getType();

        $this->assertEquals('main_scan', $type);
        $this->assertIsString($type);
    }

    public function testShouldRun(): void
    {
        // MainLibraryScanProcessor inherits from AbstractLibraryScanProcessor
        // which always returns true for shouldRun
        $this->assertTrue($this->processor->shouldRun([]));
        $this->assertTrue($this->processor->shouldRun(['dry_run' => true]));
        $this->assertTrue($this->processor->shouldRun(['force_analysis' => true]));
    }

    public function testProcessWithDefaultOptions(): void
    {
        $expectedResult = [
            'matched' => 5,
            'file_count' => 10,
            'unmatched' => ['file1.mp3', 'file2.mp3'],
        ];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, false, false)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, []);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
    }

    public function testProcessWithDryRunOption(): void
    {
        $expectedResult = [
            'matched' => 0,
            'file_count' => 0,
            'unmatched' => [],
        ];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, true, false)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, ['dry_run' => true]);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
    }

    public function testProcessWithForceAnalysisOption(): void
    {
        $expectedResult = [
            'matched' => 10,
            'file_count' => 20,
            'unmatched' => [],
        ];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, false, true)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, ['force_analysis' => true]);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
    }

    public function testProcessWithBothOptions(): void
    {
        $expectedResult = [
            'matched' => 15,
            'file_count' => 30,
            'unmatched' => ['file1.mp3'],
        ];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, true, true)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, [
            'dry_run' => true,
            'force_analysis' => true,
        ]);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
    }

    public function testProcessWithComplexOptions(): void
    {
        $expectedResult = [
            'matched' => 25,
            'file_count' => 50,
            'unmatched' => ['file1.mp3', 'file2.mp3', 'file3.mp3'],
            'path_updates' => 5,
            'removed_files' => 2,
            'updated_files' => 8,
        ];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, false, false)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, [
            'scan_type' => 'full',
            'include_hidden' => false,
            'max_depth' => 5,
        ]);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
        $this->assertEquals(25, $result['matched']);
        $this->assertEquals(50, $result['file_count']);
        $this->assertCount(3, $result['unmatched']);
    }

    public function testProcessWithEmptyResult(): void
    {
        $expectedResult = [];

        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->with($this->library, false, false)
            ->willReturn($expectedResult);

        $result = $this->processor->process($this->library, []);

        $this->assertEquals($expectedResult, $result);
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testProcessWithNullOptions(): void
    {
        $this->expectException(TypeError::class);
        $this->processor->process($this->library, null);
    }

    public function testProcessWithLibraryScannerException(): void
    {
        $this->mockLibraryScanner
            ->expects($this->once())
            ->method('scanLibrary')
            ->willThrowException(new RuntimeException('Scanner error'));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Scanner error');

        $this->processor->process($this->library, []);
    }

    public function testProcessWithDifferentLibraryInstances(): void
    {
        $library1 = new Library();
        $library1->setName('Library 1');
        $library1->setPath('/path1');

        $library2 = new Library();
        $library2->setName('Library 2');
        $library2->setPath('/path2');

        $expectedResult1 = ['matched' => 5, 'file_count' => 10];
        $expectedResult2 = ['matched' => 15, 'file_count' => 30];

        $this->mockLibraryScanner
            ->expects($this->exactly(2))
            ->method('scanLibrary')
            ->willReturnMap([
                [$library1, false, false, $expectedResult1],
                [$library2, false, false, $expectedResult2],
            ]);

        $result1 = $this->processor->process($library1, []);
        $result2 = $this->processor->process($library2, []);

        $this->assertEquals($expectedResult1, $result1);
        $this->assertEquals($expectedResult2, $result2);
    }
}
