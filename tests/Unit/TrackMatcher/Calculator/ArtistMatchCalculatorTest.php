<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\ArtistMatchCalculator;
use App\TrackMatcher\Calculator\Strategy\ArtistScoringStrategyInterface;
use PHPUnit\Framework\TestCase;

class ArtistMatchCalculatorTest extends TestCase
{
    private ArtistMatchCalculator $calculator;
    private array $strategies;

    protected function setUp(): void
    {
        // Create mock strategies
        $this->strategies = [
            $this->createMock(ArtistScoringStrategyInterface::class),
            $this->createMock(ArtistScoringStrategyInterface::class),
            $this->createMock(ArtistScoringStrategyInterface::class),
        ];

        // Create calculator with mocked strategies
        $this->calculator = new ArtistMatchCalculator($this->strategies);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(80, ArtistMatchCalculator::getPriority());
    }

    public function testGetType(): void
    {
        $this->assertEquals('artist', $this->calculator->getType());
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Artist');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(30.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(30.0, $score);
    }

    public function testCalculateScoreWithPathMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Artist');
        $pathInfo = ['artist' => 'Test Artist'];

        $this->strategies[0]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[1]->method('calculateScore')
            ->willReturn(20.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals(20.0, $score);
    }

    public function testCalculateScoreWithSimilarityMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Artist Band');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[1]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[2]->method('calculateScore')
            ->willReturn(4.25);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(4.25, $score);
    }

    public function testCalculateScoreWithNoValidMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Artist');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[1]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[2]->method('calculateScore')
            ->willReturn(0.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullArtist(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Artist');

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullUnmatchedArtist(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Artist');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn('Artist match');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Artist match', $reason);
    }

    public function testGetScoreReasonWithPathMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Artist');
        $pathInfo = ['artist' => 'Test Artist'];

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn('Directory artist match');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals('Directory artist match', $reason);
    }

    public function testGetScoreReasonWithSimilarityMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Artist Band');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[2]->method('getScoreReason')
            ->willReturn('Artist similarity (0.85)');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Artist similarity (0.85)', $reason);
    }

    public function testGetScoreReasonWithNoValidMatch(): void
    {
        $track = $this->createTrack('Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Artist');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[2]->method('getScoreReason')
            ->willReturn(null);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertNull($reason);
    }

    private function createTrack(?string $artistName): Track
    {
        $track = new Track();
        $track->setTitle('Test Track');
        $track->setTrackNumber('1');

        if ($artistName) {
            $album = new Album();
            $album->setTitle('Test Album');

            $artist = new Artist();
            $artist->setName($artistName);

            $album->setArtist($artist);
            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $artistName): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum('Test Album');
        $unmatchedTrack->setFilePath('/path/to/file.mp3');

        return $unmatchedTrack;
    }
}
