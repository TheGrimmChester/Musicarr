<?php

declare(strict_types=1);

namespace App\Tests\Unit\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\ScoreCalculatorChain;
use App\TrackMatcher\Calculator\ScoreCalculatorInterface;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorChainTest extends TestCase
{
    private ScoreCalculatorChain $scoreCalculatorChain;
    private ScoreCalculatorInterface $mockCalculator1;
    private ScoreCalculatorInterface $mockCalculator2;
    private Track $track;
    private UnmatchedTrack $unmatchedTrack;
    private array $pathInfo;

    protected function setUp(): void
    {
        $this->track = $this->createTrack();
        $this->unmatchedTrack = $this->createUnmatchedTrack();
        $this->pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $this->mockCalculator1 = $this->createMock(ScoreCalculatorInterface::class);
        $this->mockCalculator2 = $this->createMock(ScoreCalculatorInterface::class);

        $this->scoreCalculatorChain = new ScoreCalculatorChain([
            $this->mockCalculator1,
            $this->mockCalculator2,
        ]);
    }

    public function testExecuteChain(): void
    {
        $this->mockCalculator1->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(30.0);
        $this->mockCalculator1->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Title match');

        $this->mockCalculator2->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(20.0);
        $this->mockCalculator2->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Artist match');

        $result = $this->scoreCalculatorChain->executeChain($this->track, $this->unmatchedTrack, $this->pathInfo);

        $this->assertEquals(50.0, $result['score']);
        $this->assertEquals(['Title match', 'Artist match'], $result['reasons']);
    }

    public function testExecuteChainWithEmptyReasons(): void
    {
        $this->mockCalculator1->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(30.0);
        $this->mockCalculator1->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(null);

        $this->mockCalculator2->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(20.0);
        $this->mockCalculator2->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('');

        $result = $this->scoreCalculatorChain->executeChain($this->track, $this->unmatchedTrack, $this->pathInfo);

        $this->assertEquals(50.0, $result['score']);
        $this->assertEquals([], $result['reasons']); // Empty strings are filtered out
    }

    public function testExecuteChainWithNegativeScores(): void
    {
        $this->mockCalculator1->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(-10.0);
        $this->mockCalculator1->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Title penalty');

        $this->mockCalculator2->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(20.0);
        $this->mockCalculator2->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Artist match');

        $result = $this->scoreCalculatorChain->executeChain($this->track, $this->unmatchedTrack, $this->pathInfo);

        $this->assertEquals(10.0, $result['score']);
        $this->assertEquals(['Title penalty', 'Artist match'], $result['reasons']);
    }

    public function testExecuteChainWithTypes(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator1->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(30.0);
        $this->mockCalculator1->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Title match');

        $this->mockCalculator2->method('getType')->willReturn('artist');
        $this->mockCalculator2->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(20.0);
        $this->mockCalculator2->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Artist match');

        $result = $this->scoreCalculatorChain->executeChainWithTypes(
            $this->track,
            $this->unmatchedTrack,
            $this->pathInfo,
            ['title']
        );

        $this->assertEquals(30.0, $result['score']);
        $this->assertEquals(['Title match'], $result['reasons']);
    }

    public function testExecuteChainWithTypesNoMatchingTypes(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator2->method('getType')->willReturn('artist');

        $result = $this->scoreCalculatorChain->executeChainWithTypes(
            $this->track,
            $this->unmatchedTrack,
            $this->pathInfo,
            ['duration']
        );

        $this->assertEquals(0.0, $result['score']);
        $this->assertEquals([], $result['reasons']);
    }

    public function testExecuteChainWithTypesPartialMatch(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator1->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(30.0);
        $this->mockCalculator1->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Title match');

        $this->mockCalculator2->method('getType')->willReturn('artist');
        $this->mockCalculator2->method('calculateScore')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn(20.0);
        $this->mockCalculator2->method('getScoreReason')
            ->with($this->track, $this->unmatchedTrack, $this->pathInfo)
            ->willReturn('Artist match');

        $result = $this->scoreCalculatorChain->executeChainWithTypes(
            $this->track,
            $this->unmatchedTrack,
            $this->pathInfo,
            ['title', 'duration']
        );

        $this->assertEquals(30.0, $result['score']);
        $this->assertEquals(['Title match'], $result['reasons']);
    }

    public function testGetAvailableTypes(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator2->method('getType')->willReturn('artist');

        $types = $this->scoreCalculatorChain->getAvailableTypes();

        $this->assertEquals(['title', 'artist'], $types);
    }

    public function testGetAvailableTypesWithDuplicates(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator2->method('getType')->willReturn('title');

        $types = $this->scoreCalculatorChain->getAvailableTypes();

        $this->assertEquals(['title'], $types);
    }

    public function testGetCalculatorByType(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator2->method('getType')->willReturn('artist');

        $calculator = $this->scoreCalculatorChain->getCalculatorByType('title');

        $this->assertSame($this->mockCalculator1, $calculator);
    }

    public function testGetCalculatorByTypeNotFound(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('title');
        $this->mockCalculator2->method('getType')->willReturn('artist');

        $calculator = $this->scoreCalculatorChain->getCalculatorByType('duration');

        $this->assertNull($calculator);
    }

    public function testGetCalculatorByTypeCaseSensitive(): void
    {
        $this->mockCalculator1->method('getType')->willReturn('Title');
        $this->mockCalculator2->method('getType')->willReturn('title');

        $calculator = $this->scoreCalculatorChain->getCalculatorByType('title');

        $this->assertSame($this->mockCalculator2, $calculator);
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
        $track->setAlbum($album);
        $track->setTrackNumber('1');

        return $track;
    }

    private function createUnmatchedTrack(): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setFilePath('/test/path.mp3');
        $unmatchedTrack->setTitle('Test Track');
        $unmatchedTrack->setArtist('Test Artist');
        $unmatchedTrack->setAlbum('Test Album');

        return $unmatchedTrack;
    }
}
