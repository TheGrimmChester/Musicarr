<?php

declare(strict_types=1);

namespace App\Tests\Integration\UnmatchedTrackAssociation\Internal;

use App\Entity\UnmatchedTrack;
use App\UnmatchedTrackAssociation\Internal\AssociationStepChain;
use App\UnmatchedTrackAssociation\Internal\AssociationStepInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AssociationStepChainTest extends TestCase
{
    private AssociationStepChain $chain;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testExecuteChainWithSingleStep(): void
    {
        $step = $this->createMockStep('test', true, [
            'artist' => 'Test Artist',
            'album' => 'Test Album',
            'metadata' => ['genre' => 'Rock'],
        ]);

        $this->chain = new AssociationStepChain([$step]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertEquals('Test Artist', $result['artist']);
        $this->assertEquals('Test Album', $result['album']);
        $this->assertNull($result['track']);
        $this->assertEquals(['genre' => 'Rock'], $result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithMultipleSteps(): void
    {
        $step1 = $this->createMockStep('step1', true, [
            'artist' => 'Test Artist',
            'metadata' => ['genre' => 'Rock'],
        ]);

        $step2 = $this->createMockStep('step2', true, [
            'album' => 'Test Album',
            'track' => 'Test Track',
            'metadata' => ['year' => 2020],
        ]);

        $this->chain = new AssociationStepChain([$step1, $step2]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertEquals('Test Artist', $result['artist']);
        $this->assertEquals('Test Album', $result['album']);
        $this->assertEquals('Test Track', $result['track']);
        $this->assertEquals(['genre' => 'Rock', 'year' => 2020], $result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithStepThatShouldNotRun(): void
    {
        $step = $this->createMockStep('test', false, []);

        $this->chain = new AssociationStepChain([$step]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertNull($result['artist']);
        $this->assertNull($result['album']);
        $this->assertNull($result['track']);
        $this->assertEmpty($result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithContextUpdates(): void
    {
        $step1 = $this->createMockStep('step1', true, [
            'artist' => 'Test Artist',
            'metadata' => ['genre' => 'Rock'],
        ]);

        $step2 = $this->createMockStep('step2', true, [
            'album' => 'Test Album',
            'metadata' => ['artist_name' => 'Test Artist'], // Uses context from step1
        ]);

        $this->chain = new AssociationStepChain([$step1, $step2]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertEquals('Test Artist', $result['artist']);
        $this->assertEquals('Test Album', $result['album']);
        $this->assertNull($result['track']);
        $this->assertEquals(['genre' => 'Rock', 'artist_name' => 'Test Artist'], $result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithAudioAnalysis(): void
    {
        $step1 = $this->createMockStep('step1', true, [
            'audio_analysis_count' => 3,
            'errors' => ['Warning: Some analysis failed'],
        ]);

        $step2 = $this->createMockStep('step2', true, [
            'audio_analysis_count' => 2,
            'errors' => ['Error: Analysis timeout'],
        ]);

        $this->chain = new AssociationStepChain([$step1, $step2]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertNull($result['artist']);
        $this->assertNull($result['album']);
        $this->assertNull($result['track']);
        $this->assertEmpty($result['metadata']);
        $this->assertEquals(5, $result['audio_analysis_count']);
        $this->assertCount(2, $result['errors']);
        $this->assertContains('Warning: Some analysis failed', $result['errors']);
        $this->assertContains('Error: Analysis timeout', $result['errors']);
    }

    public function testExecuteChainWithEmptyResults(): void
    {
        $step = $this->createMockStep('test', true, []);

        $this->chain = new AssociationStepChain([$step]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertNull($result['artist']);
        $this->assertNull($result['album']);
        $this->assertNull($result['track']);
        $this->assertEmpty($result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithMissingResultKeys(): void
    {
        $step = $this->createMockStep('test', true, [
            'artist' => 'Test Artist',
            // Missing other keys intentionally
        ]);

        $this->chain = new AssociationStepChain([$step]);

        $unmatchedTrack = $this->createUnmatchedTrack();
        $context = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTrack, $context, $this->logger);

        $this->assertEquals('Test Artist', $result['artist']);
        $this->assertNull($result['album']);
        $this->assertNull($result['track']);
        $this->assertEmpty($result['metadata']);
        $this->assertEquals(0, $result['audio_analysis_count']);
        $this->assertEmpty($result['errors']);
    }

    private function createMockStep(string $type, bool $shouldRun, array $result): AssociationStepInterface
    {
        $step = $this->createMock(AssociationStepInterface::class);
        $step->method('getType')->willReturn($type);
        $step->method('shouldRun')->willReturn($shouldRun);
        $step->method('process')->willReturn($result);

        return $step;
    }

    private function createUnmatchedTrack(): UnmatchedTrack
    {
        $track = new UnmatchedTrack();
        $track->setTitle('Test Track');
        $track->setArtist('Test Artist');
        $track->setAlbum('Test Album');
        $track->setFilePath('/test/path/track.mp3');

        return $track;
    }
}
