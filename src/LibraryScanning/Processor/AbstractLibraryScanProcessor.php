<?php

declare(strict_types=1);

namespace App\LibraryScanning\Processor;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.library_scan_processor')]
abstract class AbstractLibraryScanProcessor implements LibraryScanProcessorInterface
{
    /**
     * Default implementation - always run unless overridden.
     */
    public function shouldRun(array $options): bool
    {
        return true;
    }

    /**
     * Merge results from multiple processors.
     */
    protected function mergeResults(array $results): array
    {
        $merged = [
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
        ];

        foreach ($results as $result) {
            $merged['unmatched'] = array_merge($merged['unmatched'], $result['unmatched'] ?? []);
            $merged['matched'] += $result['matched'] ?? 0;
            $merged['path_updates'] += $result['path_updates'] ?? 0;
            $merged['removed_files'] += $result['removed_files'] ?? 0;
            $merged['updated_files'] += $result['updated_files'] ?? 0;
            $merged['track_files_created'] += $result['track_files_created'] ?? 0;
            $merged['analysis_sent'] += $result['analysis_sent'] ?? 0;
            $merged['sync_count'] += $result['sync_count'] ?? 0;
            $merged['fix_count'] += $result['fix_count'] ?? 0;
            $merged['album_updates'] += $result['album_updates'] ?? 0;
        }

        return $merged;
    }
}
