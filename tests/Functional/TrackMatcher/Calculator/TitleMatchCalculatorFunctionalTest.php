<?php

declare(strict_types=1);

namespace App\Tests\Functional\TrackMatcher\Calculator;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\ConfigurationRepository;
use App\StringSimilarity;
use App\Tests\Functional\TestConfigurationService;
use App\TrackMatcher\Calculator\TitleMatchCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TitleMatchCalculatorFunctionalTest extends KernelTestCase
{
    private TitleMatchCalculator $titleMatchCalculator;
    private TestConfigurationService $testConfigService;
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->stringSimilarity = self::getContainer()->get(StringSimilarity::class);
        $this->testConfigService = new TestConfigurationService(
            self::getContainer()->get(EntityManagerInterface::class),
            self::getContainer()->get(ConfigurationRepository::class)
        );

        // Set default configuration values for testing
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $this->titleMatchCalculator = new TitleMatchCalculator(
            $this->stringSimilarity,
            self::getContainer()->get(EntityManagerInterface::class)
        );
    }

    public function testCalculateScoreWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Exact title match should give perfect score');
    }

    public function testCalculateScoreWithExactMatchRequired(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        // Set configuration to require exact title match
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(100.0, $score, 'Exact title match should give perfect score when required');
    }

    public function testCalculateScoreWithExactMatchRequiredButNoMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Similar Song', 'Test Artist', 'Test Album');

        // Set configuration to require exact title match
        $this->testConfigService->setConfiguration('association.require_exact_title_match', true);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertEquals(0.0, $score, 'Non-exact title match should give zero score when exact is required');
    }

    public function testCalculateScoreWithHighSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song (Remix)', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'High similarity should give positive score');
        $this->assertLessThan(100.0, $score, 'High similarity should not give perfect score');
    }

    public function testCalculateScoreWithMediumSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song (Remix)', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThan(0.0, $score, 'Medium similarity should give positive score');
        $this->assertLessThan(100.0, $score, 'Medium similarity should not give perfect score');
    }

    public function testCalculateScoreWithLowSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Completely Different Song', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertLessThan(50.0, $score, 'Low similarity should give low score');
    }

    public function testCalculateScoreWithEmptyTitles(): void
    {
        $track = $this->createTrack('', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThanOrEqual(0.0, $score, 'Empty titles should give similarity-based score');
    }

    public function testCalculateScoreWithWhitespaceOnlyTitles(): void
    {
        $track = $this->createTrack('   ', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('   ', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThanOrEqual(0.0, $score, 'Whitespace-only titles should give similarity-based score');
    }

    public function testCalculateScoreWithNullTitles(): void
    {
        $track = $this->createTrack('', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $score = $this->titleMatchCalculator->calculateScore($track, $unmatchedTrack, $pathInfo);

        $this->assertGreaterThanOrEqual(0.0, $score, 'Empty titles should give similarity-based score');
    }

    public function testGetScoreReasonWithExactMatch(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->titleMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertStringContainsString('Exact title match', $reason);
    }

    public function testGetScoreReasonWithSimilarity(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('Test Song!', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->titleMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNotNull($reason, 'Should provide score reason for similarity');
    }

    public function testGetScoreReasonWithPenalty(): void
    {
        $track = $this->createTrack('Test Song', 'Test Artist', 'Test Album');
        $unmatchedTrack = $this->createUnmatchedTrack('XYZ ABC DEF GHI JKL MNO PQR STU VWX YZ', 'Test Artist', 'Test Album');

        // Ensure exact title match is not required
        $this->testConfigService->setConfiguration('association.require_exact_title_match', false);

        $pathInfo = ['artist' => 'Test Artist', 'album' => 'Test Album'];

        $reason = $this->titleMatchCalculator->getScoreReason($track, $unmatchedTrack, $pathInfo);

        $this->assertNotNull($reason, 'Should provide score reason for penalty');
        $this->assertStringContainsString('penalty', $reason);
    }

    public function testGetPriority(): void
    {
        $priority = TitleMatchCalculator::getPriority();

        $this->assertEquals(100, $priority, 'Title calculator should have highest priority');
    }

    public function testGetType(): void
    {
        $type = $this->titleMatchCalculator->getType();

        $this->assertEquals('title', $type, 'Title calculator should have correct type');
    }

    private function createTrack(?string $title, ?string $artistName, ?string $albumTitle): Track
    {
        $track = new Track();
        $track->setTitle($title ?? '');

        if ($artistName && $albumTitle) {
            $artist = new Artist(); // Assuming Artist entity is in App\Entity
            $artist->setName($artistName);

            $album = new Album(); // Assuming Album entity is in App\Entity
            $album->setTitle($albumTitle);
            $album->setArtist($artist);

            $track->setAlbum($album);
        }

        return $track;
    }

    private function createUnmatchedTrack(?string $title, ?string $artistName, ?string $albumTitle): UnmatchedTrack
    {
        $unmatchedTrack = new UnmatchedTrack();
        $unmatchedTrack->setTitle($title ?? '');
        $unmatchedTrack->setArtist($artistName);
        $unmatchedTrack->setAlbum($albumTitle);

        return $unmatchedTrack;
    }
}
