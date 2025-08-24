<?php

declare(strict_types=1);

namespace App\LibraryScanning\Processor;

use App\Entity\Library;

interface LibraryScanProcessorInterface
{
    /**
     * Process the library scan operation.
     */
    public function process(Library $library, array $options): array;

    /**
     * Get the priority of this processor (higher number = higher priority).
     */
    public static function getPriority(): int;

    /**
     * Get the type/name of this processor.
     */
    public function getType(): string;

    /**
     * Check if this processor should run based on options.
     */
    public function shouldRun(array $options): bool;
}
