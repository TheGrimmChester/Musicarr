<?php

declare(strict_types=1);

namespace App\Task\Processor;

use App\Analyzer\Id3AudioQualityAnalyzer;
use App\Entity\Task;
use App\Repository\TrackFileRepository;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag('app.task_processor')]
class AnalyzeAudioQualityTaskProcessor implements TaskProcessorInterface
{
    public function __construct(
        private TrackFileRepository $trackFileRepository,
        private Id3AudioQualityAnalyzer $audioQualityAnalyzer,
        private LoggerInterface $logger
    ) {
    }

    public function process(Task $task): TaskProcessorResult
    {
        try {
            $trackFileId = $task->getEntityId();
            $metadata = $task->getMetadata() ?? [];
            $forceAnalysis = $metadata['force_analysis'] ?? false;

            if (!$trackFileId) {
                return TaskProcessorResult::failure('No track file ID provided');
            }

            $this->logger->info('Processing analyze audio quality task', [
                'track_file_id' => $trackFileId,
                'force_analysis' => $forceAnalysis,
            ]);

            // Get the track file
            $trackFile = $this->trackFileRepository->find($trackFileId);
            if (!$trackFile) {
                return TaskProcessorResult::failure("Track file not found with ID: {$trackFileId}");
            }

            // Check if file exists
            $filePath = $trackFile->getFilePath();
            if (!file_exists($filePath)) {
                return TaskProcessorResult::failure("Track file not found at path: {$filePath}");
            }

            // Analyze the audio quality
            $analysisResult = $this->audioQualityAnalyzer->analyzeAudioFile($filePath);

            if (!$analysisResult) {
                return TaskProcessorResult::failure("Failed to analyze audio file: {$filePath}");
            }

            if ($analysisResult['error']) {
                $fileName = basename($filePath);
                if ('' === $fileName) {
                    $fileName = 'unknown';
                }

                return TaskProcessorResult::failure("Failed to analyze audio file: {$filePath}", [
                    'track_file_id' => $trackFileId,
                    'file_path' => $filePath,
                    'error' => $analysisResult['error'],
                ]);
            }

            if (isset($analysisResult['format'])) {
                $trackFile->setFormat($analysisResult['format']);
            }
            if (isset($analysisResult['quality_string'])) {
                $trackFile->setQuality($analysisResult['quality_string']);
            }
            if (isset($analysisResult['duration'])) {
                $trackFile->setDuration((int) $analysisResult['duration']);
            }

            $this->trackFileRepository->save($trackFile, true);

            $this->logger->info('Audio quality analysis completed', [
                'track_file_id' => $trackFileId,
                'file_path' => $filePath,
                'bitrate' => $analysisResult['bitrate'] ?? null,
                'format' => $analysisResult['format'] ?? null,
                'quality' => $analysisResult['quality_string'] ?? null,
            ]);

            return TaskProcessorResult::success(
                \sprintf('Successfully analyzed audio quality for "%s"', basename($filePath)),
                [
                    'trackFileId' => $trackFile->getId(),
                    'filePath' => $filePath,
                    'bitrate' => $analysisResult['bitrate'] ?? null,
                    'sampleRate' => $analysisResult['sample_rate'] ?? null,
                    'channels' => $analysisResult['channels'] ?? null,
                    'format' => $analysisResult['format'] ?? null,
                    'quality' => $analysisResult['quality_string'] ?? null,
                    'duration' => $analysisResult['duration'] ?? null,
                ]
            );
        } catch (Exception $e) {
            $this->logger->error('Error analyzing audio quality', [
                'track_file_id' => $task->getEntityId(),
                'error' => $e->getMessage(),
            ]);

            return TaskProcessorResult::failure($e->getMessage());
        }
    }

    public function getSupportedTaskTypes(): array
    {
        return [Task::TYPE_ANALYZE_AUDIO_QUALITY];
    }

    public function supports(Task $task): bool
    {
        return Task::TYPE_ANALYZE_AUDIO_QUALITY === $task->getType();
    }
}
