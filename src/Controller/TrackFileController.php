<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Track;
use App\Entity\TrackFile;
use App\Repository\TrackRepository;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/track-file')]
class TrackFileController extends AbstractController
{
    public function __construct(
        private TrackRepository $trackRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    #[Route('/track/{id}/files', name: 'track_files', methods: ['GET'])]
    public function trackFiles(Track $track): Response
    {
        $files = $track->getFiles()->toArray();

        // Sort files by quality (best quality first)
        usort($files, function (TrackFile $a, TrackFile $b) {
            return strcmp($b->getQuality() ?? '', $a->getQuality() ?? '');
        });

        return $this->render('track_file/index.html.twig', [
            'track' => $track,
            'files' => $files,
        ]);
    }

    #[Route('/track/{id}/files', name: 'track_files_api', methods: ['GET'])]
    public function trackFilesApi(Track $track): JsonResponse
    {
        $files = [];
        foreach ($track->getFiles() as $file) {
            $files[] = [
                'id' => $file->getId(),
                'filePath' => $file->getFilePath(),
                'fileSize' => $file->getFileSize(),
                'quality' => $file->getQuality(),
                'format' => $file->getFormat(),
                'duration' => $file->getDuration(),
                'addedAt' => $file->getAddedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return $this->json([
            'track' => [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'artist' => $track->getAlbum()?->getArtist()?->getName(),
                'album' => $track->getAlbum()?->getTitle(),
            ],
            'files' => $files,
        ]);
    }

    #[Route('/file/{id}/delete', name: 'track_file_delete', methods: ['DELETE'])]
    public function deleteFile(TrackFile $file): JsonResponse
    {
        $track = $file->getTrack();
        if (null === $track) {
            return $this->json([
                'success' => false,
                'error' => 'Track not found',
            ], 404);
        }

        // Remove the file
        $track->removeFile($file);
        $this->entityManager->remove($file);

        // Update track status if no files remain
        if (0 === $track->getFiles()->count()) {
            $track->setHasFile(false);
            $track->setDownloaded(false);
        }

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'message' => 'File deleted successfully',
        ]);
    }

    #[Route('/file/{id}/reassociate', name: 'track_file_reassociate', methods: ['GET'])]
    public function reassociateForm(TrackFile $file): Response
    {
        $currentTrack = $file->getTrack();
        if (null === $currentTrack) {
            throw $this->createNotFoundException('Track not found');
        }

        $album = $currentTrack->getAlbum();
        if (null === $album) {
            throw $this->createNotFoundException('Album not found');
        }

        $artist = $album->getArtist();
        if (null === $artist) {
            throw $this->createNotFoundException('Artist not found');
        }

        $artistId = $artist->getId();
        if (null === $artistId) {
            throw $this->createNotFoundException('Artist ID not found');
        }

        // Get all tracks from the same artist
        $tracks = $this->trackRepository->findByArtist($artistId);

        return $this->render('track_file/reassociate.html.twig', [
            'file' => $file,
            'current_track' => $currentTrack,
            'artist' => $artist,
            'tracks' => $tracks,
        ]);
    }

    #[Route('/file/{id}/find-matches', name: 'track_file_find_matches', methods: ['GET'])]
    public function findMatches(TrackFile $file, Request $request): JsonResponse
    {
        $currentTrack = $file->getTrack();
        if (null === $currentTrack) {
            return $this->json([
                'success' => false,
                'error' => 'Track not found',
            ], 404);
        }

        $album = $currentTrack->getAlbum();
        if (null === $album) {
            return $this->json([
                'success' => false,
                'error' => 'Album not found',
            ], 404);
        }

        $artist = $album->getArtist();
        if (null === $artist) {
            return $this->json([
                'success' => false,
                'error' => 'Artist not found',
            ], 404);
        }

        $filePath = $file->getFilePath();
        if (null === $filePath) {
            return $this->json([
                'success' => false,
                'error' => 'File path not found',
            ], 404);
        }

        $fileName = basename($filePath);
        if ('' === $fileName) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid file path',
            ], 404);
        }

        // Extract potential track title from filename
        $potentialTitle = $this->extractTitleFromFilename($fileName);

        // Find potential matches
        $matches = [];

        // Search by exact title match
        if ($potentialTitle) {
            $artistName = $artist->getName();
            if (null !== $artistName) {
                $exactMatches = $this->trackRepository->findByArtistAndTitleFlexible($artistName, $potentialTitle);
                if ($exactMatches) {
                    $matches[] = [
                        'track' => $exactMatches,
                        'score' => 95.0, // High score for exact match, but not perfect
                        'reason' => 'Exact title match',
                    ];
                }
            }
        }

        // Search by similar titles
        if ($potentialTitle) {
            $artistId = $artist->getId();
            if (null !== $artistId) {
                $similarTracks = $this->trackRepository->findSimilarTracksByArtist($artistId, $potentialTitle);
                foreach ($similarTracks as $track) {
                    $trackTitle = $track->getTitle();
                    if (null !== $trackTitle) {
                        $similarity = $this->calculateSimilarity($potentialTitle, $trackTitle);
                        if ($similarity > 0.7) { // 70% similarity threshold
                            $matches[] = [
                                'track' => $track,
                                'score' => $similarity * 100,
                                'reason' => 'Similar title match',
                            ];
                        }
                    }
                }
            }
        }

        // Sort by score descending
        usort($matches, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Format response
        $formattedMatches = [];
        foreach ($matches as $match) {
            $track = $match['track'];
            $trackId = $track->getId();
            $trackTitle = $track->getTitle();
            $trackAlbum = $track->getAlbum();

            if (null !== $trackId && null !== $trackTitle && null !== $trackAlbum) {
                $albumTitle = $trackAlbum->getTitle();
                if (null !== $albumTitle) {
                    $formattedMatches[] = [
                        'id' => $trackId,
                        'title' => $trackTitle,
                        'album' => $albumTitle,
                        'track_number' => $track->getTrackNumber(),
                        'score' => $match['score'],
                        'reason' => $match['reason'],
                    ];
                }
            }
        }

        $currentTrackId = $currentTrack->getId();
        $currentTrackTitle = $currentTrack->getTitle();
        $currentAlbum = $currentTrack->getAlbum();

        $currentTrackData = [];
        if (null !== $currentTrackId && null !== $currentTrackTitle && null !== $currentAlbum) {
            $currentAlbumTitle = $currentAlbum->getTitle();
            if (null !== $currentAlbumTitle) {
                $currentTrackData = [
                    'id' => $currentTrackId,
                    'title' => $currentTrackTitle,
                    'album' => $currentAlbumTitle,
                ];
            }
        }

        return $this->json([
            'success' => true,
            'matches' => $formattedMatches,
            'current_track' => $currentTrackData,
        ]);
    }

    #[Route('/file/{id}/move-to-track/{trackId}', name: 'track_file_move_to_track', methods: ['POST'])]
    public function moveToTrack(TrackFile $file, int $trackId): JsonResponse
    {
        $newTrack = $this->trackRepository->find($trackId);
        if (!$newTrack) {
            return $this->json([
                'success' => false,
                'error' => 'Target track not found',
            ], 404);
        }

        $currentTrack = $file->getTrack();
        if (null === $currentTrack) {
            return $this->json([
                'success' => false,
                'error' => 'Current track not found',
            ], 404);
        }

        $currentAlbum = $currentTrack->getAlbum();
        if (null === $currentAlbum) {
            return $this->json([
                'success' => false,
                'error' => 'Current album not found',
            ], 404);
        }

        $currentArtist = $currentAlbum->getArtist();
        if (null === $currentArtist) {
            return $this->json([
                'success' => false,
                'error' => 'Current artist not found',
            ], 404);
        }

        $newAlbum = $newTrack->getAlbum();
        if (null === $newAlbum) {
            return $this->json([
                'success' => false,
                'error' => 'New album not found',
            ], 404);
        }

        $newArtist = $newAlbum->getArtist();
        if (null === $newArtist) {
            return $this->json([
                'success' => false,
                'error' => 'New artist not found',
            ], 404);
        }

        $currentArtistId = $currentArtist->getId();
        $newArtistId = $newArtist->getId();

        // Verify both tracks belong to the same artist
        if (null === $currentArtistId || null === $newArtistId || $currentArtistId !== $newArtistId) {
            return $this->json([
                'success' => false,
                'error' => 'Can only move files between tracks of the same artist',
            ], 400);
        }

        try {
            // Remove from current track
            $currentTrack->removeFile($file);

            // Add to new track
            $newTrack->addFile($file);

            // Update track statuses
            if (0 === $currentTrack->getFiles()->count()) {
                $currentTrack->setHasFile(false);
                $currentTrack->setDownloaded(false);
            }

            $newTrack->setHasFile(true);
            $newTrack->setDownloaded(true);

            $this->entityManager->flush();

            $newTrackId = $newTrack->getId();
            $newTrackTitle = $newTrack->getTitle();
            $newAlbumTitle = $newAlbum->getTitle();

            $newTrackData = [];
            if (null !== $newTrackId && null !== $newTrackTitle && null !== $newAlbumTitle) {
                $newTrackData = [
                    'id' => $newTrackId,
                    'title' => $newTrackTitle,
                    'album' => $newAlbumTitle,
                ];
            }

            return $this->json([
                'success' => true,
                'message' => 'File moved successfully',
                'new_track' => $newTrackData,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error moving file: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Extract potential track title from filename.
     */
    private function extractTitleFromFilename(?string $filename): ?string
    {
        if (null === $filename) {
            return null;
        }

        // Remove extension
        $filenameWithoutExt = pathinfo($filename, \PATHINFO_FILENAME);
        if ('' === $filenameWithoutExt) {
            return null;
        }

        // Remove track number patterns (e.g., "01 - ", "01.", "01 ")
        $filename = preg_replace('/^\d+\s*[-.\s]\s*/', '', $filenameWithoutExt);
        if (null === $filename) {
            return null;
        }

        // Remove artist name if present (assuming format: "Artist - Title")
        if (false !== mb_strpos($filename, ' - ')) {
            $parts = explode(' - ', $filename, 2);
            if (2 === \count($parts)) {
                $filename = $parts[1];
            }
        }

        $trimmed = mb_trim($filename);

        return '' !== $trimmed ? $trimmed : null;
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $str1 = mb_strtolower(mb_trim($str1));
        $str2 = mb_strtolower(mb_trim($str2));

        if ($str1 === $str2) {
            return 1.0;
        }

        $maxLength = max(mb_strlen($str1), mb_strlen($str2));
        if (0 === $maxLength) {
            return 0.0;
        }

        $distance = levenshtein($str1, $str2);

        return 1 - ($distance / $maxLength);
    }
}
