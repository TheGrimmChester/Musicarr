<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation\Internal;

use App\Entity\Album;
use App\Entity\UnmatchedTrack;
use App\Manager\AlbumStatusManager;
use Psr\Log\LoggerInterface;

class AlbumStatusUpdateStep extends AbstractAssociationStep
{
    public function __construct(
        private AlbumStatusManager $albumStatusManager
    ) {
    }

    public static function getPriority(): int
    {
        return 50; // Run after track file creation
    }

    public function getType(): string
    {
        return 'album_status_update';
    }

    public function process(UnmatchedTrack $unmatchedTrack, array $context, LoggerInterface $logger): array
    {
        $track = $context['track'] ?? null;
        $album = $context['album'] ?? null;

        if (!$track) {
            return ['errors' => ['No track available for album status update']];
        }

        // Get album from track if not provided in context
        if (!$album instanceof Album) {
            $album = $track->getAlbum();
        }

        if (!$album instanceof Album) {
            return ['errors' => ['No album available for status update']];
        }

        // Update album status using the manager
        $this->albumStatusManager->updateAlbumStatus($album);

        return [
            'metadata' => ['album_status_updated' => true],
        ];
    }
}
