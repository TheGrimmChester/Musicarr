<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\TrackNumberMatchCalculator;
use PHPUnit\Framework\TestCase;

class TrackNumberMatchCalculatorTest extends TestCase
{
    private TrackNumberMatchCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new TrackNumberMatchCalculator();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(20, TrackNumberMatchCalculator::getPriority());
    }

    public function testGetType(): void
    {
        $this->assertEquals('trackNumber', $this->calculator->getType());
    }

    public function testCalculateScoreWithExactTrackNumberMatch(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(5);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference1(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(6);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference2(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(7);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference3(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(8);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference4(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(9);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference5(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(10);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference6(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(11);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(-5.0, $score);
    }

    public function testCalculateScoreWithTrackNumberDifference10(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(15);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(-5.0, $score);
    }

    public function testCalculateScoreWithNullTrackNumber(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack(5);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullUnmatchedTrackNumber(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithBothNullTrackNumbers(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithInvalidEntities(): void
    {
        $track = new Track();
        $unmatchedTrack = new UnmatchedTrack();

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithTrackNumberZero(): void
    {
        $track = $this->createTrack(0);
        $unmatchedTrack = $this->createUnmatchedTrack(0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithLargeTrackNumbers(): void
    {
        $track = $this->createTrack(100);
        $unmatchedTrack = $this->createUnmatchedTrack(100);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(15.0, $score);
    }

    public function testGetScoreReasonWithExactTrackNumberMatch(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(5);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertEquals('Track number match', $reason);
    }

    public function testGetScoreReasonWithTrackNumberDifference1(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(6);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithTrackNumberDifference6(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(11);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertEquals('Track number mismatch (difference: 6)', $reason);
    }

    public function testGetScoreReasonWithTrackNumberDifference10(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(15);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertEquals('Track number mismatch (difference: 10)', $reason);
    }

    public function testGetScoreReasonWithNullTrackNumber(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack(5);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNullUnmatchedTrackNumber(): void
    {
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithBothNullTrackNumbers(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithInvalidEntities(): void
    {
        $track = new Track();
        $unmatchedTrack = new UnmatchedTrack();

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testCalculateScoreWithDifferentOrder(): void
    {
        // Test that order doesn't matter for track number difference calculation
        $track = $this->createTrack(10);
        $unmatchedTrack = $this->createUnmatchedTrack(5);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithBoundaryCase(): void
    {
        // Test the boundary case where difference is exactly 5
        $track = $this->createTrack(5);
        $unmatchedTrack = $this->createUnmatchedTrack(10);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    private function createTrack(?int $trackNumber): Track
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setAlbum($album);
        if (null !== $trackNumber) {
            $track->setTrackNumber((string) $trackNumber);
        }

        return $track;
    }

    private function createUnmatchedTrack(?int $trackNumber): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum('Test Album');
        $unmatchedTrack->setTrackNumber(null !== $trackNumber ? (string) $trackNumber : null);

        return $unmatchedTrack;
    }
}
