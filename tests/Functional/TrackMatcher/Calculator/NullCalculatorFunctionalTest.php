<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\NullCalculator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class NullCalculatorFunctionalTest extends KernelTestCase
{
    private NullCalculator $nullCalculator;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->nullCalculator = new NullCalculator();
    }

    public function testCalculateScoreAlwaysReturnsZero(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should always return zero score');
    }

    public function testCalculateScoreWithDifferentData(): void
    {
        $track = $this->createTrack('Different Song', 'Different Artist', 'Different Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should return zero score regardless of data');
    }

    public function testCalculateScoreWithMissingAlbum(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should return zero score even with missing album');
    }

    public function testCalculateScoreWithMissingArtist(): void
    {
        $track = $this->createTrackWithoutArtist('Test Song', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should return zero score even with missing artist');
    }

    public function testCalculateScoreWithEmptyPathInfo(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = [];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should return zero score even with empty path info');
    }

    public function testGetScoreReasonAlwaysReturnsNull(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->nullCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason, 'Null calculator should always return null reason');
    }

    public function testGetScoreReasonWithDifferentData(): void
    {
        $track = $this->createTrack('Different Song', 'Different Artist', 'Different Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->nullCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason, 'Null calculator should return null reason regardless of data');
    }

    public function testGetScoreReasonWithMissingData(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->nullCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason, 'Null calculator should return null reason even with missing data');
    }

    public function testGetPriority(): void
    {
        $priority = NullCalculator::getPriority();

        $this->assertEquals(0, $priority, 'Null calculator should have lowest priority');
    }

    public function testGetType(): void
    {
        $type = $this->nullCalculator->getType();

        $this->assertEquals('null', $type, 'Null calculator should have correct type');
    }

    public function testCalculateScoreWithEmptyEntities(): void
    {
        $track = new Track();
        $unmatchedTrack = new UnmatchedTrack();

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->nullCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null calculator should return zero score with empty entities');
    }

    public function testGetScoreReasonWithEmptyEntities(): void
    {
        $track = new Track();
        $unmatchedTrack = new UnmatchedTrack();

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->nullCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason, 'Null calculator should return null reason with empty entities');
    }

    private function createTrack(?string $title, ?string $artistName, ?string $albumTitle): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($artistName && $albumTitle) {
            $artist = new Artist();
            $artist->setName($artistName);

            $album = new Album();
            $album->setTitle($albumTitle);
            $album->setArtist($artist);

            $track->setAlbum($album);
        }

        return $track;
    }

    private function createTrackWithoutAlbum(?string $title, ?string $artistName): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($artistName) {
            $track->setArtistName($artistName);
        }

        return $track;
    }

    private function createTrackWithoutArtist(?string $title, ?string $albumTitle): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($albumTitle) {
            $album = new Album();
            $album->setTitle($albumTitle);
            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);

        return $unmatchedTrack;
    }
}
