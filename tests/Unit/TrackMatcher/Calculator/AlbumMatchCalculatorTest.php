<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\AlbumMatchCalculator;
use App\TrackMatcher\Calculator\Strategy\AlbumScoringStrategyInterface;
use PHPUnit\Framework\TestCase;

class AlbumMatchCalculatorTest extends TestCase
{
    private AlbumMatchCalculator $calculator;
    private array $strategies;

    protected function setUp(): void
    {
        // Create mock strategies
        $this->strategies = [
            $this->createMock(AlbumScoringStrategyInterface::class),
            $this->createMock(AlbumScoringStrategyInterface::class),
            $this->createMock(AlbumScoringStrategyInterface::class),
        ];

        // Create calculator with mocked strategies
        $this->calculator = new AlbumMatchCalculator($this->strategies);
    }

    public function testGetPriority(): void
    {
        $this->assertEquals(60, AlbumMatchCalculator::getPriority());
    }

    public function testGetType(): void
    {
        $this->assertEquals('album', $this->calculator->getType());
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(25.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(25.0, $score);
    }

    public function testCalculateScoreWithPathMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Album');
        $pathInfo = ['album' => 'Test Album'];

        $this->strategies[0]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[1]->method('calculateScore')
            ->willReturn(15.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals(15.0, $score);
    }

    public function testCalculateScoreWithSimilarityMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album (Deluxe)');

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
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Album');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[1]->method('calculateScore')
            ->willReturn(0.0);

        $this->strategies[2]->method('calculateScore')
            ->willReturn(0.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullAlbum(): void
    {
        $track = $this->createTrack(null);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album');

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testCalculateScoreWithNullUnmatchedAlbum(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack(null);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(0.0, $score);
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn('Exact album match');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Exact album match', $reason);
    }

    public function testGetScoreReasonWithPathMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Album');
        $pathInfo = ['album' => 'Test Album'];

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn('Path album match');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, $pathInfo);
        $this->assertEquals('Path album match', $reason);
    }

    public function testGetScoreReasonWithSimilarityMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album (Deluxe)');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[2]->method('getScoreReason')
            ->willReturn('Similarity match');

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertEquals('Similarity match', $reason);
    }

    public function testGetScoreReasonWithNoValidMatch(): void
    {
        $track = $this->createTrack('Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Different Album');

        $this->strategies[0]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[1]->method('getScoreReason')
            ->willReturn(null);

        $this->strategies[2]->method('getScoreReason')
            ->willReturn(null);

        $reason = $this->calculator->getScoreReason($track, $unmatchedTrack, []);
        $this->assertNull($reason);
    }

    public function testCleanMethodRemovesSpecialCharacters(): void
    {
        $track = $this->createTrack('Test: Album - Title');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Album Title');

        $this->strategies[0]->method('calculateScore')
            ->willReturn(25.0);

        $score = $this->calculator->calculateScore($track, $unmatchedTrack, []);
        $this->assertEquals(25.0, $score);
    }

    private function createTrack(?string $albumTitle): Track
    {
        $track = new Track();
        $track->setTitle('Test Track');
        $track->setTrackNumber('1');

        if ($albumTitle) {
            $album = new Album();
            $album->setTitle($albumTitle);

            $artist = new Artist();
            $artist->setName('Test Artist');

            $album->setArtist($artist);
            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $albumTitle): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setFilePath('/path/to/file.mp3');

        return $unmatchedTrack;
    }
}
