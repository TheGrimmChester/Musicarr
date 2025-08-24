<?php

declare(strict_types=1);

namespace App\LibraryScanning;

use App\Entity\Library;
use App\LibraryScanning\Processor\LibraryScanProcessorInterface;
use Symfony\Component\DependencyInjection\Attribute\TaggedIterator;

class LibraryScanChain
{
    /**
     * @param LibraryScanProcessorInterface[] $processors
     */
    public function __construct(
        #[TaggedIterator('app.library_scan_processor', defaultPriorityMethod: 'getPriority')]
        private iterable $processors
    ) {
    }

    /**
     * Execute the complete chain of processors for a library.
     */
    public function executeChain(Library $library, array $options): array
    {
        $results = [];

        foreach ($this->processors as $processor) {
            if (!$processor->shouldRun($options)) {
                continue;
            }

            $result = $processor->process($library, $options);
            $results[] = $result;
        }

        return $this->mergeResults($results);
    }

    /**
     * Execute chain with specific processor types.
     */
    public function executeChainWithTypes(Library $library, array $options, array $types): array
    {
        $results = [];

        foreach ($this->processors as $processor) {
            if (!\in_array($processor->getType(), $types, true)) {
                continue;
            }

            if (!$processor->shouldRun($options)) {
                continue;
            }

            $result = $processor->process($library, $options);
            $results[] = $result;
        }

        return $this->mergeResults($results);
    }

    /**
     * Get available processor types.
     */
    public function getAvailableTypes(): array
    {
        $types = [];
        foreach ($this->processors as $processor) {
            $types[] = $processor->getType();
        }

        return array_unique($types);
    }

    /**
     * Get processor by type.
     */
    public function getProcessorByType(string $type): ?LibraryScanProcessorInterface
    {
        foreach ($this->processors as $processor) {
            if ($processor->getType() === $type) {
                return $processor;
            }
        }

        return null;
    }

    /**
     * Merge results from multiple processors.
     */
    private function mergeResults(array $results): array
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
            'track_status_fixes' => 0,
            'auto_associations' => 0,
            'album_updates' => 0,
            'file_count' => 0,
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
            $merged['track_status_fixes'] += $result['track_status_fixes'] ?? 0;
            $merged['auto_associations'] += $result['auto_associations'] ?? 0;
            $merged['album_updates'] += $result['album_updates'] ?? 0;
            $merged['file_count'] += $result['file_count'] ?? 0;
        }

        return $merged;
    }
}
