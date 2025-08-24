<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\NullCalculator;
use PHPUnit\Framework\TestCase;

class NullCalculatorTest extends TestCase
{
    private NullCalculator $calculator;

    protected function setUp(): void
    {
        $this->calculator = new NullCalculator();
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(0, NullCalculator::getPriority());
    }

    public function testGetType(): void
    {
        $this->assertEquals('null', $this->calculator->getType());
    }

    public function testCalculateScoreAlwaysReturnsZero(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullEntities(): void
    {
        $track = new Track(); // Empty track instead of null
        $unmatchedTrack = new UnmatchedTrack(); // Empty unmatched track instead of null

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithEmptyPathInfo(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithComplexPathInfo(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album', 'year' => 2020];

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithInvalidTrack(): void
    {
        $track = new Track(); // Empty track
        $unmatchedTrack = $this->createUnmatchedTrack();

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithInvalidUnmatchedTrack(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = new UnmatchedTrack(); // Empty unmatched track

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonAlwaysReturnsNull(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithNullEntities(): void
    {
        $track = new Track(); // Empty track instead of null
        $unmatchedTrack = new UnmatchedTrack(); // Empty unmatched track instead of null

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithEmptyPathInfo(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithComplexPathInfo(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album', 'year' => 2020];

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithInvalidTrack(): void
    {
        $track = new Track(); // Empty track
        $unmatchedTrack = $this->createUnmatchedTrack();

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testGetScoreReasonWithInvalidUnmatchedTrack(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = new UnmatchedTrack(); // Empty unmatched track

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason);
    }

    public function testCalculateScoreConsistency(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        // Multiple calls should return the same result
        $score1 = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $score2 = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $score3 = $this->calculator->calculateScore($track, $unmatchedTrack, []);

        $this->assertEquals(0.0, $score1);
        $this->assertEquals(0.0, $score2);
        $this->assertEquals(0.0, $score3);
        $this->assertEquals($score1, $score2);
        $this->assertEquals($score2, $score3);
    }

    public function testGetScoreReasonConsistency(): void
    {
        $track = $this->createTrack();
        $unmatchedTrack = $this->createUnmatchedTrack();

        // Multiple calls should return the same result
        $reason1 = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $reason2 = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $reason3 = $this->calculator->getScoreReason($track, $unmatchedTrack, []);

        $this->assertNull($reason1);
        $this->assertNull($reason2);
        $this->assertNull($reason3);
        $this->assertEquals($reason1, $reason2);
        $this->assertEquals($reason2, $reason3);
    }

    private function createTrack(): Track
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        $album = new Album();
        $album->setTitle('Test Album');
        $album->setArtist($artist);

        $track = new Track();
        $track->setTitle('Test Track');
        $track->setTrackNumber('1');
        $track->setAlbum($album);

        return $track;
    }

    private function createUnmatchedTrack(): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum('Test Album');
        $unmatchedTrack->setTrackNumber('1');

        return $unmatchedTrack;
    }
}
