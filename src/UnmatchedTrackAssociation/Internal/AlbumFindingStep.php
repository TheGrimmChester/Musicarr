<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\UnmatchedTrack;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use Psr\Log\LoggerInterface;

class AlbumFindingStep extends AbstractAssociationStep
{
    public function __construct(
        private AlbumRepository $albumRepository,
        private ArtistRepository $artistRepository
    ) {
    }

    public static function getPriority(): int
    {
        return 80; // Run after artist finding
    }

    public function getType(): string
    {
        return 'album_finding';
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        $artist = $context['artist'] ?? null;
        if (!$artist) {
            return ['errors' => ['No artist available for album search']];
        }

        // If artist is a string (from extracted metadata), we need to find the Artist entity first
        if (\is_string($artist)) {
            $artistEntity = $this->artistRepository->findOneBy(['name' => $artist]);
            if (!$artistEntity) {
                return ['errors' => ["Artist not found: {$artist}"]];
            }
            $artist = $artistEntity;
        }

        // Use extracted metadata if available, otherwise fall back to unmatched track metadata
        $albumName = $context['album'] ?? $unmatchedTrack->getAlbum();

        if (empty($albumName)) {
            return ['errors' => ['No album name available']];
        }

        // Try to find album by title and artist with flexible matching
        $album = $this->albumRepository->findByTitleAndArtistFlexible($albumName, $artist);

        if (!$album) {
            return ['errors' => ["Album not found: {$albumName} for artist: {$artist->getName()}"]];
        }

        return [
            'album' => $album,
            'metadata' => ['album_found' => true],
        ];
    }
}
