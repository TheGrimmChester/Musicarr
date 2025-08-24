<?php

declare(strict_types=1);

namespace App\Tests\Integration\UnmatchedTrackAssociation;

use App\Entity\UnmatchedTrack;
use App\UnmatchedTrackAssociation\UnmatchedTrackAssociationChain;
use App\UnmatchedTrackAssociation\UnmatchedTrackAssociationProcessorInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class UnmatchedTrackAssociationChainTest extends TestCase
{
    private UnmatchedTrackAssociationChain $chain;
    private LoggerInterface $logger;

    protected function setUp(): void
    {
        $this->logger = $this->createMock(LoggerInterface::class);
    }

    public function testExecuteChainWithSingleProcessor(): void
    {
        $processor = $this->createMockProcessor('test', true, [
            'associated_count' => 5,
            'not_found_count' => 2,
            'errors' => [],
        ]);

        $this->chain = new UnmatchedTrackAssociationChain([$processor]);

        $unmatchedTracks = $this->createUnmatchedTracks(10);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(5, $result['associated_count']);
        $this->assertEquals(2, $result['not_found_count']);
        $this->assertEquals(0, $result['no_artist_count']);
        $this->assertEquals(0, $result['audio_analysis_dispatched']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithMultipleProcessors(): void
    {
        $processor1 = $this->createMockProcessor('processor1', true, [
            'associated_count' => 3,
            'not_found_count' => 1,
            'errors' => ['Error 1'],
        ]);

        $processor2 = $this->createMockProcessor('processor2', true, [
            'associated_count' => 2,
            'not_found_count' => 1,
            'errors' => ['Error 2'],
        ]);

        $this->chain = new UnmatchedTrackAssociationChain([$processor1, $processor2]);

        $unmatchedTracks = $this->createUnmatchedTracks(10);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(5, $result['associated_count']);
        $this->assertEquals(2, $result['not_found_count']);
        $this->assertEquals(0, $result['no_artist_count']);
        $this->assertEquals(0, $result['audio_analysis_dispatched']);
        $this->assertCount(2, $result['errors']);
        $this->assertContains('Error 1', $result['errors']);
        $this->assertContains('Error 2', $result['errors']);
    }

    public function testExecuteChainWithProcessorThatShouldNotRun(): void
    {
        $processor = $this->createMockProcessor('test', false, []);

        $this->chain = new UnmatchedTrackAssociationChain([$processor]);

        $unmatchedTracks = $this->createUnmatchedTracks(5);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(0, $result['associated_count']);
        $this->assertEquals(0, $result['not_found_count']);
        $this->assertEquals(0, $result['no_artist_count']);
        $this->assertEquals(0, $result['audio_analysis_dispatched']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithMixedProcessorResults(): void
    {
        $processor1 = $this->createMockProcessor('processor1', true, [
            'associated_count' => 10,
            'not_found_count' => 5,
            'no_artist_count' => 2,
            'audio_analysis_dispatched' => 3,
            'errors' => ['Warning: Some tracks skipped'],
        ]);

        $processor2 = $this->createMockProcessor('processor2', true, [
            'associated_count' => 5,
            'not_found_count' => 1,
            'no_artist_count' => 0,
            'audio_analysis_dispatched' => 2,
            'errors' => [],
        ]);

        $this->chain = new UnmatchedTrackAssociationChain([$processor1, $processor2]);

        $unmatchedTracks = $this->createUnmatchedTracks(20);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(15, $result['associated_count']);
        $this->assertEquals(6, $result['not_found_count']);
        $this->assertEquals(2, $result['no_artist_count']);
        $this->assertEquals(5, $result['audio_analysis_dispatched']);
        $this->assertCount(1, $result['errors']);
        $this->assertContains('Warning: Some tracks skipped', $result['errors']);
    }

    public function testExecuteChainWithEmptyResults(): void
    {
        $processor = $this->createMockProcessor('test', true, []);

        $this->chain = new UnmatchedTrackAssociationChain([$processor]);

        $unmatchedTracks = $this->createUnmatchedTracks(0);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(0, $result['associated_count']);
        $this->assertEquals(0, $result['not_found_count']);
        $this->assertEquals(0, $result['no_artist_count']);
        $this->assertEquals(0, $result['audio_analysis_dispatched']);
        $this->assertEmpty($result['errors']);
    }

    public function testExecuteChainWithMissingResultKeys(): void
    {
        $processor = $this->createMockProcessor('test', true, [
            'associated_count' => 5,
            // Missing other keys intentionally
        ]);

        $this->chain = new UnmatchedTrackAssociationChain([$processor]);

        $unmatchedTracks = $this->createUnmatchedTracks(10);
        $options = ['library_id' => 1];

        $result = $this->chain->executeChain($unmatchedTracks, $options, $this->logger);

        $this->assertEquals(5, $result['associated_count']);
        $this->assertEquals(0, $result['not_found_count']);
        $this->assertEquals(0, $result['no_artist_count']);
        $this->assertEquals(0, $result['audio_analysis_dispatched']);
        $this->assertEmpty($result['errors']);
    }

    private function createMockProcessor(string $type, bool $shouldRun, array $result): UnmatchedTrackAssociationProcessorInterface
    {
        $processor = $this->createMock(UnmatchedTrackAssociationProcessorInterface::class);
        $processor->method('getType')->willReturn($type);
        $processor->method('shouldRun')->willReturn($shouldRun);
        $processor->method('process')->willReturn($result);

        return $processor;
    }

    private function createUnmatchedTracks(int $count): array
    {
        $tracks = [];
        for ($i = 0; $i < $count; ++$i) {
            $track = new UnmatchedTrack();
            $track->setTitle("Test Track $i");
            $track->setArtist("Test Artist $i");
            $track->setAlbum("Test Album $i");
            $track->setFilePath("/test/path/track$i.mp3");
            $tracks[] = $track;
        }

        return $tracks;
    }
}
