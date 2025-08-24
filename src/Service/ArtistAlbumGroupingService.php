<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Album;

class ArtistAlbumGroupingService
{
    /**
     * Group albums by release group and type.
     */
    public function groupAlbumsByReleaseGroupAndType(array $albums): array
    {
        $albumsByReleaseGroup = [];
        $albumsByType = [
            'Album' => [],
            'Single' => [],
            'EP' => [],
            'Compilation' => [],
        ];

        foreach ($albums as $album) {
            $this->addAlbumToTypeGroup($album, $albumsByType);
            $this->addAlbumToReleaseGroup($album, $albumsByReleaseGroup);
        }

        return [
            'albumsByType' => $albumsByType,
            'albumsByReleaseGroup' => $albumsByReleaseGroup,
        ];
    }

    /**
     * Get available statuses for filtering.
     */
    public function getAvailableStatuses(array $albums): array
    {
        $statuses = array_unique(array_map(function (Album $album) {
            return $album->getStatus();
        }, $albums));

        return array_filter($statuses); // Remove null values
    }

    private function addAlbumToTypeGroup(Album $album, array &$albumsByType): void
    {
        $type = $album->getAlbumType();
        if (isset($albumsByType[$type])) {
            $albumsByType[$type][] = $album;
        }
    }

    private function addAlbumToReleaseGroup(Album $album, array &$albumsByReleaseGroup): void
    {
        $releaseGroupId = $album->getReleaseGroupMbid();

        if ($releaseGroupId) {
            $this->addAlbumToExistingReleaseGroup($album, $releaseGroupId, $albumsByReleaseGroup);
        } else {
            $this->addAlbumToNoGroupCategory($album, $albumsByReleaseGroup);
        }
    }

    private function addAlbumToExistingReleaseGroup(Album $album, string $releaseGroupId, array &$albumsByReleaseGroup): void
    {
        if (!isset($albumsByReleaseGroup[$releaseGroupId])) {
            $albumsByReleaseGroup[$releaseGroupId] = [
                'id' => $releaseGroupId,
                'title' => $album->getTitle(), // Use first album title as group title
                'type' => $album->getAlbumType(),
                'albums' => [],
            ];
        }
        $albumsByReleaseGroup[$releaseGroupId]['albums'][] = $album;
    }

    private function addAlbumToNoGroupCategory(Album $album, array &$albumsByReleaseGroup): void
    {
        if (!isset($albumsByReleaseGroup['no_group'])) {
            $albumsByReleaseGroup['no_group'] = [
                'id' => 'no_group',
                'title' => 'Albums without Release Group',
                'type' => 'Unknown',
                'albums' => [],
            ];
        }
        $albumsByReleaseGroup['no_group']['albums'][] = $album;
    }
}
