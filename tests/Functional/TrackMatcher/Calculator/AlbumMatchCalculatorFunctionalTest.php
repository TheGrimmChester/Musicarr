<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Configuration\ConfigurationService;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\StringSimilarity;
use App\TrackMatcher\Calculator\AlbumMatchCalculator;
use App\TrackMatcher\Calculator\Strategy\ExactAlbumMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\PathMatchScoringStrategy;
use App\TrackMatcher\Calculator\Strategy\SimilarityScoringStrategy;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AlbumMatchCalculatorFunctionalTest extends KernelTestCase
{
    private AlbumMatchCalculator $albumMatchCalculator;
    private ConfigurationService $configurationService;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->stringSimilarity = self::getContainer()->get(StringSimilarity::class);
        $this->configurationService = self::getContainer()->get(ConfigurationService::class);

        // Create strategies manually since the container might not have them in test environment
        $strategies = [
            new ExactAlbumMatchScoringStrategy(),
            new PathMatchScoringStrategy(),
            new SimilarityScoringStrategy($this->stringSimilarity),
        ];

        $this->albumMatchCalculator = new AlbumMatchCalculator($strategies);
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(25.0, $score, 'Exact album match should give expected score');
    }

    public function testCalculateScoreWithHighSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album!');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album!'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'High similarity should give positive score');
        $this->assertLessThan(40.0, $score, 'High similarity should not give perfect score');
    }

    public function testCalculateScoreWithMediumSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album (Deluxe Edition)');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album (Deluxe Edition)'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Medium similarity below threshold should give neutral score');
    }

    public function testCalculateScoreWithLowSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Completely Different Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Completely Different Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Low similarity not below penalty threshold should give neutral score');
    }

    public function testCalculateScoreWithEmptyAlbumNames(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', '');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', '');

        $pathInfo = ['artist' => 'Test Artist', 'album' => ''];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Empty album names should give zero score');
    }

    public function testCalculateScoreWithWhitespaceOnlyAlbumNames(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', '   ');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', '   ');

        $pathInfo = ['artist' => 'Test Artist', 'album' => '   '];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(25.0, $score, 'Whitespace-only album names should be treated as equal after cleaning');
    }

    public function testCalculateScoreWithNullAlbumNames(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', null);
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', null);

        $pathInfo = ['artist' => 'Test Artist', 'album' => null];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Null album names should give zero score');
    }

    public function testCalculateScoreWithMissingAlbum(): void
    {
        $track = $this->createTrackWithoutAlbum('Test Song', 'Test Artist');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without album should give zero score');
    }

    public function testCalculateScoreWithMissingArtist(): void
    {
        $track = $this->createTrackWithoutArtist('Test Song', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Track without artist should give zero score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->albumMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Exact album match', $reason);
    }

    public function testGetScoreReasonWithSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album!');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album!'];

        $reason = $this->albumMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('similarity', $reason);
    }

    public function testGetScoreReasonWithPenalty(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Completely Different Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Completely Different Album'];

        $reason = $this->albumMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertTrue(null === $reason || str_contains($reason, 'mismatch'), 'Should return null or mismatch reason for different albums');
    }

    public function testGetPriority(): void
    {
        $priority = AlbumMatchCalculator::getPriority();

        $this->assertEquals(60, $priority, 'Album calculator should have correct priority');
    }

    public function testGetType(): void
    {
        $type = $this->albumMatchCalculator->getType();

        $this->assertEquals('album', $type, 'Album calculator should have correct type');
    }

    public function testCalculateScoreWithPathInfoAlbum(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Different Album');

        // Path info has the correct album
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'Path info album match should give positive score');
    }

    public function testCalculateScoreWithPathInfoAlbumMismatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        // Path info has different album
        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Different Album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(40.0, $score, 'Path info album mismatch should reduce score');
    }

    public function testCalculateScoreWithSpecialCharacters(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album (2020)');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album (2020)');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album (2020)'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(25.0, $score, 'Album names with special characters should match exactly');
    }

    public function testCalculateScoreWithCaseInsensitiveMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'test album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'test album'];

        $score = $this->albumMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(25.0, $score, 'Case-insensitive album names should match exactly');
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

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title);
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);

        return $unmatchedTrack;
    }
}
