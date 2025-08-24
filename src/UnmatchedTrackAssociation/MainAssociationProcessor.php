<?php

declare(strict_types=1);

namespace App\UnmatchedTrackAssociation;

use App\Entity\Track;
use App\Entity\UnmatchedTrack;
use App\Repository\UnmatchedTrackRepository;
use App\UnmatchedTrackAssociation\Internal\AssociationStepChain;
use DateTime;
use Psr\Log\LoggerInterface;

class MainAssociationProcessor extends AbstractUnmatchedTrackAssociationProcessor
{
    public function __construct(
        private UnmatchedTrackRepository $unmatchedTrackRepo,
        private AssociationStepChain $associationStepChain
    ) {
    }

    public static function getPriority(): int
    {
        return 100; // Highest priority - main association operation
    }

    public function getType(): string
    {
        return 'main_association';
    }

    public function process(array $unmatchedTracks, array $options, LoggerInterface $logger): array
    {
        $stats = [
            'associated_count' => 0,
            'not_found_count' => 0,
            'no_artist_count' => 0,
            'audio_analysis_count' => 0,
        ];
        $errors = [];
        $results = [];

        foreach ($unmatchedTracks as $unmatchedTrack) {
            if ($unmatchedTrack->isMatched()) {
                continue;
            }

            $result = $this->processUnmatchedTrack($unmatchedTrack, $options, $logger);
            $results[] = $result;

            $this->updateStats($result, $stats);
            $errors = array_merge($errors, $result['errors']);
        }

        return [
            'results' => $results,
            'associated_count' => $stats['associated_count'],
            'not_found_count' => $stats['not_found_count'],
            'no_artist_count' => $stats['no_artist_count'],
            'audio_analysis_dispatched' => $stats['audio_analysis_count'],
            'errors' => $errors,
        ];
    }

    private function processUnmatchedTrack(UnmatchedTrack $unmatchedTrack, array $options, LoggerInterface $logger): array
    {
        $context = [
            'dry_run' => $options['dry_run'] ?? false,
        ];

        $result = $this->associationStepChain->executeChain($unmatchedTrack, $context, $logger);

        if ($result['track']) {
            $this->logMatchDetails($unmatchedTrack, $result, $logger);
            $this->markTrackAsMatched($unmatchedTrack, $options);
        }

        return $result;
    }

    private function updateStats(array $result, array &$stats): void
    {
        if ($result['track']) {
            ++$stats['associated_count'];
            $stats['audio_analysis_count'] += $result['audio_analysis_count'] ?? 0;

            return;
        }

        ++$stats['not_found_count'];
    }

    private function logMatchDetails(UnmatchedTrack $unmatchedTrack, array $result, LoggerInterface $logger): void
    {
        /** @var Track|null $track */
        $track = $result['track'];
        $score = $result['score'] ?? null;

        if (!$track || !$track->getAlbum() || !$track->getAlbum()->getArtist()) {
            return;
        }

        $unmatchedTitle = $unmatchedTrack->getTitle() ?: $unmatchedTrack->getFileName();
        $foundTitle = $track->getTitle();
        $artistName = $track->getAlbum()->getArtist()->getName() ?? 'Unknown Artist';

        if (null !== $score) {
            $scoreFormatted = number_format($score, 1);
            $scoreQuality = $this->getScoreQuality($score);
            $logger->info("✓ Association réussie: '{$unmatchedTitle}' -> '{$foundTitle}' par {$artistName} {$scoreQuality} Score: {$scoreFormatted}/100");
        }
    }

    private function getScoreQuality(float $score): string
    {
        if ($score >= 80) {
            return '🟢';
        }
        if ($score >= 60) {
            return '🟡';
        }
        if ($score >= 40) {
            return '🟠';
        }

        return '🔴';
    }

    private function markTrackAsMatched(UnmatchedTrack $unmatchedTrack, array $options): void
    {
        if (!isset($options['dry_run']) || true !== $options['dry_run']) {
            $unmatchedTrack->setIsMatched(true);
            $unmatchedTrack->setLastAttemptedMatch(new DateTime());
            $this->unmatchedTrackRepo->save($unmatchedTrack, true);
        }
    }
}
