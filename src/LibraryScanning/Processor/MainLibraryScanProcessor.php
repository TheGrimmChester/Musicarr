<?php

declare(strict_types=1);

namespace App\LibraryScanning\Processor;

use App\Entity\Library;
use App\Scanner\LibraryScanner;

class MainLibraryScanProcessor extends AbstractLibraryScanProcessor
{
    public function __construct(
        private LibraryScanner $libraryScanner
    ) {
    }

    public static function getPriority(): int
    {
        return 100; // Highest priority - main scanning operation
    }

    public function getType(): string
    {
        return 'main_scan';
    }

    public function process(Library $library, array $options): array
    {
        $dryRun = $options['dry_run'] ?? false;
        $forceAnalysis = $options['force_analysis'] ?? false;

        $result = $this->libraryScanner->scanLibrary($library, $dryRun, $forceAnalysis);

        return $result;
    }
}
