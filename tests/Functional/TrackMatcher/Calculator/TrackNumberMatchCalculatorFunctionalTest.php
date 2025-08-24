<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\TrackMatcher\Calculator\TrackNumberMatchCalculator;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TrackNumberMatchCalculatorFunctionalTest extends KernelTestCase
{
    private TrackNumberMatchCalculator $trackNumberMatchCalculator;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->trackNumberMatchCalculator = new TrackNumberMatchCalculator();
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Exact track number match should give expected score');
    }

    public function testCalculateScoreWithDifferentTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '02');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Different track numbers should give zero score');
    }

    public function testCalculateScoreWithMissingTrackNumber(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without track number should give zero score');
    }

    public function testCalculateScoreWithMissingUnmatchedTrackNumber(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', null);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Unmatched track without track number should give zero score');
    }

    public function testCalculateScoreWithEmptyTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Empty track numbers should match and give full score');
    }

    public function testCalculateScoreWithWhitespaceOnlyTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '   ');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '   ');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Whitespace-only track numbers should match and give full score');
    }

    public function testCalculateScoreWithMissingAlbum(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without album should give zero score');
    }

    public function testCalculateScoreWithMissingArtist(): void
    {
        $track = $this->createTrackWithoutArtist('Test Song', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without artist should give zero score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '01');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->trackNumberMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals('Track number match', $reason);
    }

    public function testGetScoreReasonWithNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '01');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '02');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->trackNumberMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNull($reason);
    }

    public function testGetPriority(): void
    {
        $priority = TrackNumberMatchCalculator::getPriority();

        $this->assertEquals(20, $priority, 'Track number calculator should have correct priority');
    }

    public function testGetType(): void
    {
        $type = $this->trackNumberMatchCalculator->getType();

        $this->assertEquals('trackNumber', $type, 'Track number calculator should have correct type');
    }

    public function testCalculateScoreWithSingleDigitTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '1');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '1');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Single digit track numbers should match correctly');
    }

    public function testCalculateScoreWithDoubleDigitTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '10');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '10');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Double digit track numbers should match correctly');
    }

    public function testCalculateScoreWithTrackNumbersWithLeadingZeros(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '001');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '001');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Track numbers with leading zeros should match correctly');
    }

    public function testCalculateScoreWithTrackNumbersWithSpecialCharacters(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '1A');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '1A');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Track numbers with special characters should match correctly');
    }

    public function testCalculateScoreWithTrackNumbersWithSpaces(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', ' 1 ');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', ' 1 ');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Track numbers with spaces should match correctly');
    }

    public function testCalculateScoreWithVinylTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 'A1');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 'A1');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(15.0, $score, 'Vinyl track numbers should match correctly');
    }

    public function testCalculateScoreWithVinylTrackNumbersSameSide(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 'A1');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 'A2');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(10.0, $score, 'Vinyl track numbers on same side should give good score');
    }

    public function testCalculateScoreWithVinylTrackNumbersDifferentSide(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 'A1');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 'B1');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Vinyl track numbers on different sides should give zero score');
    }

    public function testCalculateScoreWithNumericTrackNumbersClose(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '5');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '6');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Close numeric track numbers should give zero score');
    }

    public function testCalculateScoreWithNumericTrackNumbersFar(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', '1');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', '10');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(-5.0, $score, 'Far numeric track numbers should give penalty score');
    }

    public function testCalculateScoreWithNonNumericTrackNumbers(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album', 'ABC');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album', 'XYZ');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->trackNumberMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Non-numeric track numbers should give zero score');
    }

    private function createTrack(?string $title, ?string $artistName, ?string $albumTitle, ?string $trackNumber): Track
    {
        $track = new Track();
        $track->setTitle($title);
        $track->setTrackNumber($trackNumber ?? '');

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

    private function createTrackWithoutAlbum(?string $title, ?string $artistName, ?string $trackNumber): Track
    {
        $track = new Track();
        $track->setTitle($title);
        $track->setTrackNumber($trackNumber ?? '');

        if ($artistName) {
            $track->setArtistName($artistName);
        }

        return $track;
    }

    private function createTrackWithoutArtist(?string $title, ?string $albumTitle, ?string $trackNumber): Track
    {
        $track = new Track();
        $track->setTitle($title);
        $track->setTrackNumber($trackNumber ?? '');

        if ($albumTitle) {
            $album = new Album();
            $album->setTitle($albumTitle);
            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle, ?string $trackNumber): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);
        $unmatchedTrack->setTrackNumber($trackNumber);

        return $unmatchedTrack;
    }
}
