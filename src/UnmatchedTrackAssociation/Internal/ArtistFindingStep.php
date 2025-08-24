<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\UnmatchedTrack;
use App\Repository\ArtistRepository;
use Psr\Log\LoggerInterface;

class ArtistFindingStep extends AbstractAssociationStep
{
    public function __construct(
        private ArtistRepository $artistRepository
    ) {
    }

    public static function getPriority(): int
    {
        return 100; // High priority - find artist after metadata extraction
    }

    public function getType(): string
    {
        return 'artist_finding';
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        // Use extracted metadata if available, otherwise fall back to unmatched track metadata
        $artistName = $context['artist'] ?? $unmatchedTrack->getArtist();

        if (empty($artistName)) {
            return ['errors' => ['No artist name available']];
        }

        // Try to find artist by name
        $artist = $this->artistRepository->findOneBy(['name' => $artistName]);

        if (!$artist) {
            return ['errors' => ["Artist not found: {$artistName}"]];
        }

        return [
            'artist' => $artist,
            'metadata' => ['artist_found' => true],
        ];
    }
}
