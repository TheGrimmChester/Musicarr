<?php

declare(strict_types=1);

namespace App\TrackMatcher\Calculator;

use App\Entity\Track;

abstract class AbstractScoreCalculator implements ScoreCalculatorInterface
{
    /**
     * Validate that required entities exist.
     */
    protected function validateEntities(Track $track): bool
    {
        $album = $track->getAlbum();
        if (null === $album) {
            return false;
        }

        $artist = $album->getArtist();
        if (null === $artist) {
            return false;
        }

        return true;
    }
}
