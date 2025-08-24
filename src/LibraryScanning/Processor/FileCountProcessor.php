<?php

declare(strict_types=1);

namespace App\LibraryScanning\Processor;

use App\Entity\Library;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class FileCountProcessor extends AbstractLibraryScanProcessor
{
    public function __construct()
    {
    }

    public static function getPriority(): int
    {
        return 10; // Run first to get file count
    }

    public function getType(): string
    {
        return 'file_count';
    }

    public function process(Library $library, array $options): array
    {
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

        $fileCount = $this->countFiles($path);

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
            'file_count' => $fileCount,
        ];
    }

    private function countFiles(string $path): int
    {
        $count = 0;
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($iterator as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile()) {
                ++$count;
            }
        }

        return $count;
    }
}
