<?php

declare(strict_types=1);

namespace App\LibraryScanning\Processor;

use App\Entity\Library;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class EmptyDirectoryCleanupProcessor extends AbstractLibraryScanProcessor
{
    public function __construct()
    {
    }

    public static function getPriority(): int
    {
        return 50; // Run after main scan
    }

    public function getType(): string
    {
        return 'empty_directory_cleanup';
    }

    public function shouldRun(array $options): bool
    {
        return $options['clean_empty_dirs'] ?? false;
    }

    public function process(Library $library, array $options): array
    {
        $dryRun = $options['dry_run'] ?? false;
        $path = $library->getPath();

        if (null === $path) {
            return [
                'unmatched' => [],
                'matched' => 0,
                'path_updates' => 0,
                'removed_files' => 0,
                'updated_files' => 0,
                'track_files_created' => 0,
                'analysis_sent' => 0,
                'sync_count' => 0,
                'fix_count' => 0,
                'album_updates' => 0,
                'file_count' => 0,
            ];
        }

        $this->cleanEmptyDirectories($path, $dryRun);

        return [
            'unmatched' => [],
            'matched' => 0,
            'path_updates' => 0,
            'removed_files' => 0,
            'updated_files' => 0,
            'track_files_created' => 0,
            'analysis_sent' => 0,
            'sync_count' => 0,
            'fix_count' => 0,
            'album_updates' => 0,
            'file_count' => 0,
        ];
    }

    private function cleanEmptyDirectories(string $path, bool $dryRun): array
    {
        $removedDirs = 0;
        $processedDirs = 0;

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isDir()) {
                ++$processedDirs;

                // Check if directory is empty
                $files = scandir($file->getPathname());
                if (!$files || \count($files) > 2) { // Not empty (more than . and ..)
                    continue;
                }

                if ($dryRun) {
                    continue;
                }

                if (rmdir($file->getPathname())) {
                    ++$removedDirs;
                }
            }
        }

        return [
            'removed_dirs' => $removedDirs,
            'processed_dirs' => $processedDirs,
        ];
    }
}
