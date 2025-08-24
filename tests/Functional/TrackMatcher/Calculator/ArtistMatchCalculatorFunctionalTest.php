<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Configuration\Config\ConfigurationFactory;
use App\Configuration\ConfigurationService;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use App\TrackMatcher\Calculator\ArtistMatchCalculator;
use App\TrackMatcher\Calculator\Strategy\ArtistPathMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\ArtistSimilarityScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\ExactArtistMatchScoringStrategy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class ArtistMatchCalculatorFunctionalTest extends KernelTestCase
{
    private ArtistMatchCalculator $artistMatchCalculator;
    private ConfigurationService $configurationService;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->stringSimilarity = self::getContainer()->get(StringSimilarity::class);
        $this->configurationService = self::getContainer()->get(ConfigurationService::class);

        // Create strategies manually since the container might not have them in test environment
        $strategies = [
            new ExactArtistMatchScoringStrategy(
                self::getContainer()->get(ConfigurationFactory::class)
            ),
            new ArtistPathMatchScoringStrategy(),
            new ArtistSimilarityScoringStrategy($this->stringSimilarity),
        ];

        $this->artistMatchCalculator = new ArtistMatchCalculator($strategies);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(30.0, $score, 'Exact artist match should give expected score');
    }

    public function testCalculateScoreWithHighSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist!', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist!', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'High similarity should give positive score');
        $this->assertLessThan(50.0, $score, 'High similarity should not give perfect score');
    }

    public function testCalculateScoreWithMediumSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist (Band)', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist (Band)', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Medium similarity below threshold should give neutral score');
    }

    public function testCalculateScoreWithLowSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Completely Different Artist', 'Test Album');

        $pathInfo = ['artist' => 'Completely Different Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Low similarity not below penalty threshold should give neutral score');
    }

    public function testCalculateScoreWithEmptyArtistNames(): void
    {
        $track = $this->createTrack('Test Song', '', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', '', 'Test Album');

        $pathInfo = ['artist' => '', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Empty artist names should give zero score');
    }

    public function testCalculateScoreWithWhitespaceOnlyArtistNames(): void
    {
        $track = $this->createTrack('Test Song', '   ', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', '   ', 'Test Album');

        $pathInfo = ['artist' => '   ', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(30.0, $score, 'Whitespace-only artist names should be treated as equal after cleaning');
    }

    public function testCalculateScoreWithNullArtistNames(): void
    {
        $track = $this->createTrack('Test Song', null, 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', null, 'Test Album');

        $pathInfo = ['artist' => null, 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null artist names should give zero score');
    }

    public function testCalculateScoreWithMissingAlbum(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without album should give zero score');
    }

    public function testCalculateScoreWithMissingArtist(): void
    {
        $track = $this->createTrackWithoutArtist('Test Song', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without artist should give zero score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->artistMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Artist match', $reason);
    }

    public function testGetScoreReasonWithSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist!', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist!', 'album' => 'Test Album'];

        $reason = $this->artistMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('similarity', $reason);
    }

    public function testGetScoreReasonWithPenalty(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Completely Different Artist', 'Test Album');

        $pathInfo = ['artist' => 'Completely Different Artist', 'album' => 'Test Album'];

        $reason = $this->artistMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertTrue(null === $reason || str_contains($reason, 'mismatch'), 'Should return null or mismatch reason for different artists');
    }

    public function testGetPriority(): void
    {
        $priority = ArtistMatchCalculator::getPriority();

        $this->assertEquals(80, $priority, 'Artist calculator should have correct priority');
    }

    public function testGetType(): void
    {
        $type = $this->artistMatchCalculator->getType();

        $this->assertEquals('artist', $type, 'Artist calculator should have correct type');
    }

    public function testCalculateScoreWithPathInfoArtist(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Different Artist', 'Test Album');

        // Path info has the correct artist
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'Path info artist match should give positive score');
    }

    public function testCalculateScoreWithPathInfoArtistMismatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        // Path info has different artist
        $pathInfo = ['artist' => 'Different Artist', 'album' => 'Test Album'];

        $score = $this->artistMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(50.0, $score, 'Path info artist mismatch should reduce score');
    }

    private function createTrack(?string $title, ?string $artistName, string $albumTitle): Track
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

    private function createTrackWithoutAlbum(string $title, string $artistName): Track
    {
        $track = new Track();
        $track->setTitle($title);

        if ($artistName) {
            $track->setArtistName($artistName);
        }

        return $track;
    }

    private function createTrackWithoutArtist(string $title, string $albumTitle): Track
    {
        $track = new Track();
        $track->setTitle($title);

        $album = new Album();
        $album->setTitle($albumTitle);

        $track->setAlbum($album);

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, string $albumTitle): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);

        return $unmatchedTrack;
    }
}
