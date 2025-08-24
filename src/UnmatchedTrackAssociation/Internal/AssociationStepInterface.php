<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\UnmatchedTrack;
use Psr\Log\LoggerInterface;

interface AssociationStepInterface
{
    /**
     * Process the association step.
     */
    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array;

    /**
     * Get the priority of this step (higher number = higher priority).
     */
    public static function getPriority(): int;

    /**
     * Get the type/name of this step.
     */
    public function getType(): string;

    /**
     * Check if this step should run based on context.
     */
    public function shouldRun(array $context): bool;
}
