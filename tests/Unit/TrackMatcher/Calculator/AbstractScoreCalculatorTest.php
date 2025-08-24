<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\AbstractScoreCalculator;
use App\TrackMatcher\Calculator\ScoreCalculatorInterface;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class AbstractScoreCalculatorTest extends TestCase
{
    public function testValidateEntitiesWithValidEntities(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track = $this->createValidTrack();
        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertTrue($result);
    }

    public function testValidateEntitiesWithTrackWithoutAlbum(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track = new Track(); // No album
        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertFalse($result);
    }

    public function testValidateEntitiesWithAlbumWithoutArtist(): void
    {
        $calculator = new ConcreteTestCalculator();

        $album = new Album();
        $album->setTitle('Test Album');
        // No artist set

        $track = new Track();
        $track->setAlbum($album);

        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertFalse($result);
    }

    public function testValidateEntitiesWithNullAlbum(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track = new Track();
        $track->setAlbum(null);

        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertFalse($result);
    }

    public function testValidateEntitiesWithNullArtist(): void
    {
        $calculator = new ConcreteTestCalculator();

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist(null);

        $track = new Track();
        $track->setAlbum($album);

        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertFalse($result);
    }

    public function testValidateEntitiesWithEmptyTrack(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track = new Track();
        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertFalse($result);
    }

    public function testValidateEntitiesWithCompleteTrackStructure(): void
    {
        $calculator = new ConcreteTestCalculator();

        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setAlbum($album);
        $track->setTrackNumber('1');

        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack);

        $this->assertTrue($result);
    }

    public function testValidateEntitiesWithMultipleTracks(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track1 = $this->createValidTrack();
        $track2 = $this->createValidTrack();
        $unmatchedTrack = $this->createValidUnmatchedTrack();

        $result1 = $this->invokeValidateEntities($calculator, $track1, $unmatchedTrack);
        $result2 = $this->invokeValidateEntities($calculator, $track2, $unmatchedTrack);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    public function testValidateEntitiesWithDifferentUnmatchedTracks(): void
    {
        $calculator = new ConcreteTestCalculator();

        $track = $this->createValidTrack();
        $unmatchedTrack1 = $this->createValidUnmatchedTrack();
        $unmatchedTrack2 = $this->createValidUnmatchedTrack();

        $result1 = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack1);
        $result2 = $this->invokeValidateEntities($calculator, $track, $unmatchedTrack2);

        $this->assertTrue($result1);
        $this->assertTrue($result2);
    }

    private function invokeValidateEntities(AbstractScoreCalculator $calculator, Track $track, UnmatchedTrack $unmatchedTrack): bool
    {
        $reflection = new ReflectionClass($calculator);
        $method = $reflection->getMethod('validateEntities');
        $method->setAccessible(true);

        return $method->invoke($calculator, $track, $unmatchedTrack);
    }

    private function createValidTrack(): Track
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setAlbum($album);
        $track->setTrackNumber('1');

        return $track;
    }

    private function createValidUnmatchedTrack(): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum('Test Album');

        return $unmatchedTrack;
    }
}

/**
 * Concrete implementation of AbstractScoreCalculator for testing purposes.
 */
class ConcreteTestCalculator extends AbstractScoreCalculator implements ScoreCalculatorInterface
{
    public static function getPriority(): int
    {
        return 50;
    }

    public function getType(): string
    {
        return 'test';
    }

    public function calculateScore(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): float
    {
        return 0.0;
    }

    public function getScoreReason(Track $track, UnmatchedTrack $unmatchedTrack, array $pathInfo): ?string
    {
        return null;
    }
}
