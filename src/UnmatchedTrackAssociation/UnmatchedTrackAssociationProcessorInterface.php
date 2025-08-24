<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation;

use Psr\Log\LoggerInterface;

interface UnmatchedTrackAssociationProcessorInterface
{
    /**
     * Process the unmatched track association operation.
     */
    public function process(array $unmatchedTracks, array $options, LoggerInterface $logger): array;

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
