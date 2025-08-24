<?php

declare(strict_types=1);

namespace App\Controller;

use App\Analyzer\FilePathAnalyzer;
use App\Client\MusicBrainzApiClient;
use App\Entity\Task;
use App\Entity\TrackFile;
use App\Entity\UnmatchedTrack;
use App\Http\ResponseFormatter;
use App\Repository\AlbumRepository;
use App\Repository\ArtistRepository;
use App\Repository\LibraryRepository;
use App\Repository\TrackRepository;
use App\Repository\UnmatchedTrackRepository;
use App\StringSimilarity;
use App\Task\TaskFactory;
use App\TrackMatcher\TrackMatcher;
use App\UnmatchedTrackAssociation\Internal\AssociationStepChain;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/unmatched-tracks')]
class UnmatchedTrackController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UnmatchedTrackRepository $unmatchedTrackRepository,
        private LibraryRepository $libraryRepository,
        private ArtistRepository $artistRepository,
        private TrackRepository $trackRepository,
        private TaskFactory $taskService,
        private LoggerInterface $logger,
        private StringSimilarity $stringSimilarityService,
        private FilePathAnalyzer $filePathAnalyzerService,
        private TrackMatcher $trackMatchingService,
        private ResponseFormatter $responseFormatter,
        private AssociationStepChain $associationStepChain,
        private readonly MusicBrainzApiClient $musicBrainzApiClient,
        private AlbumRepository $albumRepository,
    ) {
    }

    #[Route('/scan-libraries', name: 'app_scan_libraries', methods: ['GET'])]
    public function scanLibraries(): Response
    {
        $libraries = $this->libraryRepository->findBy(['enabled' => true]);
        $enabledLibrariesCount = \count($libraries);

        return $this->render('unmatched_track/scan.html.twig', [
            'libraries' => $libraries,
            'enabledLibrariesCount' => $enabledLibrariesCount,
        ]);
    }

    #[Route('/', name: 'unmatched_tracks_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $libraryId = $request->query->get('library');
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');
        $album = $request->query->get('album');

        $libraries = $this->libraryRepository->findBy(['enabled' => true]);
        $enabledLibrariesCount = \count($libraries);

        // Convert parameters for pagination
        $libraryIdInt = null;
        if ($libraryId && is_numeric($libraryId)) {
            $libraryIdInt = (int) $libraryId;
        }
        $artistStr = \is_string($artist) && !empty($artist) ? $artist : null;
        $titleStr = \is_string($title) && !empty($title) ? $title : null;
        $albumStr = \is_string($album) && !empty($album) ? $album : null;

        // Load only first page for initial render (50 items)
        $initialLimit = 50;
        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedPaginated(
            1, // First page
            $initialLimit,
            $libraryIdInt,
            $artistStr,
            $titleStr,
            $albumStr
        );

        // Get total count for stats
        $totalCount = $this->unmatchedTrackRepository->countUnmatchedTotal(
            $libraryIdInt,
            $artistStr,
            $titleStr,
            $albumStr
        );

        $stats = [
            'total' => $totalCount,
            'loaded' => \count($unmatchedTracks),
            'hasMore' => $totalCount > $initialLimit,
            'byLibrary' => [],
        ];

        foreach ($libraries as $library) {
            $libraryId = $library->getId();
            if (null !== $libraryId) {
                $stats['byLibrary'][$library->getName()] = $this->unmatchedTrackRepository->countUnmatchedByLibrary($libraryId);
            }
        }

        return $this->render('unmatched_track/index.html.twig', [
            'unmatched_tracks' => $unmatchedTracks,
            'libraries' => $libraries,
            'stats' => $stats,
            'selected_library' => $libraryId,
            'search_artist' => $artist,
            'search_title' => $title,
            'search_album' => $album,
            'enabledLibrariesCount' => $enabledLibrariesCount,
        ]);
    }

    // Routes API pour le JavaScript - DOIT ÊTRE AVANT LES ROUTES AVEC PARAMÈTRES

    #[Route('/list', name: 'unmatched_tracks_list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        $libraryId = $request->query->get('library');
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');

        if ($libraryId) {
            $libraryIdInt = is_numeric($libraryId) ? (int) $libraryId : null;
            if (null !== $libraryIdInt) {
                $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedByLibrary($libraryIdInt);
            } else {
                $unmatchedTracks = [];
            }
        } elseif ($artist || $title) {
            $artistStr = \is_string($artist) ? $artist : null;
            $titleStr = \is_string($title) ? $title : null;
            $unmatchedTracks = $this->unmatchedTrackRepository->findByArtistAndTitle($artistStr, $titleStr);
        } else {
            $unmatchedTracks = $this->unmatchedTrackRepository->findBy(['isMatched' => false], ['discoveredAt' => 'DESC']);
        }

        $data = [];
        foreach ($unmatchedTracks as $track) {
            $data[] = [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'artistName' => $track->getArtist(),
                'albumTitle' => $track->getAlbum(),
                'year' => $track->getYear(),
                'path' => $track->getFilePath(),
                'fileSize' => $track->getFileSize(),
                'duration' => $track->getDuration(),
                'discoveredAt' => $track->getDiscoveredAt()?->format('Y-m-d H:i:s'),
                'library' => [
                    'id' => $track->getLibrary()->getId(),
                    'name' => $track->getLibrary()->getName(),
                ],
            ];
        }

        return $this->json($data);
    }

    #[Route('/paginated', name: 'unmatched_tracks_paginated', methods: ['GET'])]
    public function paginated(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 50))); // Between 10 and 100
        $libraryId = $request->query->get('library');
        $artist = $request->query->get('artist');
        $title = $request->query->get('title');
        $album = $request->query->get('album');

        // Convert library ID to int if provided
        $libraryIdInt = null;
        if ($libraryId && is_numeric($libraryId)) {
            $libraryIdInt = (int) $libraryId;
        }

        // Convert search strings
        $artistStr = \is_string($artist) && !empty($artist) ? $artist : null;
        $titleStr = \is_string($title) && !empty($title) ? $title : null;
        $albumStr = \is_string($album) && !empty($album) ? $album : null;

        // Get paginated results
        $unmatchedTracks = $this->unmatchedTrackRepository->findUnmatchedPaginated(
            $page,
            $limit,
            $libraryIdInt,
            $artistStr,
            $titleStr,
            $albumStr
        );

        // Get total count for pagination
        $total = $this->unmatchedTrackRepository->countUnmatchedTotal(
            $libraryIdInt,
            $artistStr,
            $titleStr,
            $albumStr
        );

        // Format data
        $data = [];
        foreach ($unmatchedTracks as $track) {
            $data[] = [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'artist' => $track->getArtist(),
                'album' => $track->getAlbum(),
                'trackNumber' => $track->getTrackNumber(),
                'year' => $track->getYear(),
                'fileName' => $track->getFileName(),
                'relativePath' => $track->getRelativePath(),
                'duration' => $track->getDuration(),
                'fileSize' => $track->getFileSize(),
                'discoveredAt' => $track->getDiscoveredAt()?->format('Y-m-d H:i:s'),
                'hasLyrics' => $track->hasLyrics(),
                'library' => [
                    'id' => $track->getLibrary()->getId(),
                    'name' => $track->getLibrary()->getName(),
                ],
            ];
        }

        return $this->responseFormatter->paginatedResponse($data, $page, $limit, $total);
    }

    #[Route('/scan', name: 'unmatched_tracks_scan', methods: ['POST'])]
    public function scan(): JsonResponse
    {
        try {
            $libraries = $this->libraryRepository->findBy(['enabled' => true]);

            foreach ($libraries as $library) {
                $libraryId = $library->getId();
                if (null !== $libraryId) {
                    $this->taskService->createTask(
                        Task::TYPE_SCAN_LIBRARY,
                        null,
                        $libraryId,
                        $library->getName(),
                        ['dry_run' => false, 'force_analysis' => false],
                        3
                    );
                }
            }

            return $this->json([
                'success' => true,
                'message' => 'Scan started for all libraries',
                'libraries_count' => \count($libraries),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error starting scan: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error starting scan',
            ], 500);
        }
    }

    #[Route('/associate', name: 'unmatched_tracks_associate', methods: ['POST'])]
    public function associate(Request $request): JsonResponse
    {
        /** @var array{trackId: int, artistName: string, mbid: string} $data */
        $data = json_decode($request->getContent(), true);

        if (!$data['trackId'] || !$data['artistName'] || !$data['mbid']) {
            return $this->json([
                'success' => false,
                'error' => 'Track ID, artist name and MBID are required',
            ], 400);
        }

        try {
            $track = $this->unmatchedTrackRepository->find($data['trackId']);
            if (!$track) {
                return $this->json([
                    'success' => false,
                    'error' => 'Track not found',
                ], 404);
            }

            $trackId = $track->getId();
            $library = $track->getLibrary();
            if (null === $trackId || null === $library) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid track or library',
                ], 400);
            }

            $libraryId = $library->getId();
            if (null === $libraryId) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid library ID',
                ], 400);
            }

            $this->taskService->createTask(
                Task::TYPE_ASSOCIATE_ARTIST,
                $data['mbid'],
                $trackId,
                $data['artistName'],
                ['library_id' => $libraryId],
                3
            );

            return $this->json([
                'success' => true,
                'message' => 'Association task created successfully',
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error creating association task: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error creating association task',
            ], 500);
        }
    }

    #[Route('/auto-associate', name: 'unmatched_tracks_auto_associate', methods: ['POST'])]
    public function autoAssociate(Request $request): JsonResponse
    {
        try {
            /** @var array{libraryId?: int, dryRun?: bool, limit?: int} $data */
            $data = json_decode($request->getContent(), true) ?? [];
            $libraryId = $data['libraryId'] ?? null;
            $dryRun = $data['dryRun'] ?? false;
            $limit = $data['limit'] ?? 50;

            // Validate library ID if provided
            $library = null;
            if (null !== $libraryId) {
                $library = $this->libraryRepository->find($libraryId);
                if (!$library) {
                    return $this->json(['error' => 'Library not found'], 404);
                }
            }

            $libraryName = $library ? $library->getName() : 'all libraries';

            // Create auto association task
            $this->taskService->createTask(
                Task::TYPE_AUTO_ASSOCIATE_TRACKS,
                null,
                $libraryId,
                $libraryName,
                ['dry_run' => $dryRun, 'limit' => $limit],
                3
            );

            return $this->json([
                'success' => true,
                'message' => "Auto association started for {$libraryName} in background",
                'library_id' => $libraryId,
                'dry_run' => $dryRun,
                'limit' => $limit,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error starting auto association: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error starting auto association',
            ], 500);
        }
    }

    #[Route('/auto-associate-selected', name: 'unmatched_tracks_auto_associate_selected', methods: ['POST'])]
    public function autoAssociateSelected(Request $request): JsonResponse
    {
        try {
            /** @var array{trackIds: array<int>} $data */
            $data = json_decode($request->getContent(), true) ?? [];
            $trackIds = $data['trackIds'] ?? [];

            if (empty($trackIds)) {
                return $this->json(['error' => 'No track IDs provided'], 400);
            }

            // Validate that all tracks exist and are unmatched
            $unmatchedTracks = $this->unmatchedTrackRepository->findBy(['id' => $trackIds]);

            if (\count($unmatchedTracks) !== \count($trackIds)) {
                return $this->json(['error' => 'Some tracks not found or already matched'], 400);
            }

            $createdTasks = 0;
            $errors = [];

            foreach ($unmatchedTracks as $unmatchedTrack) {
                try {
                    $trackId = $unmatchedTrack->getId();
                    $library = $unmatchedTrack->getLibrary();

                    if (null === $trackId || null === $library) {
                        $errors[] = "Track ID {$trackId} has invalid data";
                        continue;
                    }

                    $libraryId = $library->getId();
                    if (null === $libraryId) {
                        $errors[] = "Track ID {$trackId} has invalid library";
                        continue;
                    }

                    // Create auto association task for individual track
                    $this->taskService->createTask(
                        Task::TYPE_AUTO_ASSOCIATE_TRACK,
                        null,
                        $trackId,
                        $unmatchedTrack->getArtist() ?? 'Unknown Artist',
                        [
                            'library_id' => $libraryId,
                            'track_title' => $unmatchedTrack->getTitle(),
                            'album_title' => $unmatchedTrack->getAlbum(),
                            'file_path' => $unmatchedTrack->getFilePath(),
                        ],
                        3
                    );

                    ++$createdTasks;
                } catch (Exception $e) {
                    $this->logger->error("Error creating auto association task for track {$trackId}: " . $e->getMessage());
                    $errors[] = "Error processing track ID {$trackId}";
                }
            }

            if (empty($errors)) {
                return $this->json([
                    'success' => true,
                    'message' => "Successfully created {$createdTasks} auto association tasks",
                    'created_tasks' => $createdTasks,
                ]);
            } else {
                return $this->json([
                    'success' => true,
                    'message' => "Created {$createdTasks} tasks with {$errors} errors",
                    'created_tasks' => $createdTasks,
                    'errors' => $errors,
                ]);
            }
        } catch (Exception $e) {
            $this->logger->error('Error creating bulk auto association tasks: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error creating bulk auto association tasks',
            ], 500);
        }
    }

    // Routes avec paramètres - DOIT ÊTRE APRÈS LES ROUTES SANS PARAMÈTRES

    #[Route('/{id}', name: 'unmatched_track_show', methods: ['GET'])]
    public function show(UnmatchedTrack $unmatchedTrack): Response
    {
        return $this->render('unmatched_track/show.html.twig', [
            'unmatched_track' => $unmatchedTrack,
        ]);
    }

    #[Route('/{id}/suggest-artists', name: 'unmatched_track_suggest_artists', methods: ['GET'])]
    public function suggestArtists(UnmatchedTrack $unmatchedTrack): JsonResponse
    {
        $artistName = $unmatchedTrack->getArtist();

        if (!$artistName) {
            return $this->json(['error' => $this->translator->trans('api.error.no_artist_name_available')], 400);
        }

        try {
            // Use the MusicBrainz API client to search for artists
            $suggestions = $this->musicBrainzApiClient->searchArtist($artistName);

            return $this->json([
                'suggestions' => $suggestions,
                'original_artist' => $artistName,
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.artist_search_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.search_error')], 500);
        }
    }

    #[Route('/{id}/suggest-albums/{artistMbid}', name: 'unmatched_track_suggest_albums', methods: ['GET'])]
    public function suggestAlbums(UnmatchedTrack $unmatchedTrack, string $artistMbid): JsonResponse
    {
        try {
            // Use the MusicBrainz API client to search for albums
            $albumTitle = $unmatchedTrack->getAlbum();
            $artistName = $unmatchedTrack->getArtist();

            if ($albumTitle && $artistName) {
                // Search for specific album
                $albums = $this->musicBrainzApiClient->searchAlbum($albumTitle, $artistName);
            } else {
                // Get all albums for the artist
                $albums = $this->musicBrainzApiClient->getArtistAlbums($artistMbid);
            }

            return $this->json([
                'suggestions' => $albums,
                'original_album' => $albumTitle,
                'artist_mbid' => $artistMbid,
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.album_search_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.album_search_error')], 500);
        }
    }

    #[Route('/{id}/add-album', name: 'unmatched_track_add_album', methods: ['POST'])]
    public function addAlbum(Request $request, UnmatchedTrack $unmatchedTrack): JsonResponse
    {
        $releaseMbid = $request->request->get('releaseMbid');
        $releaseGroupMbid = $request->request->get('releaseGroupMbid');
        $albumTitle = $request->request->get('title');
        $artistMbid = $request->request->get('artistMbid');
        $artistName = $request->request->get('artistName');

        $library = $unmatchedTrack->getLibrary();
        if (null === $library) {
            return $this->json(['error' => 'Library not found'], 400);
        }
        $libraryId = $library->getId();
        if (null === $libraryId) {
            return $this->json(['error' => 'Library ID not found'], 400);
        }

        if (!$releaseMbid || !$albumTitle || !$artistMbid || !$artistName) {
            return $this->json(['error' => $this->translator->trans('api.error.all_parameters_required')], 400);
        }

        $unmatchedTrackId = $unmatchedTrack->getId();
        if (null === $unmatchedTrackId) {
            return $this->json(['error' => 'Unmatched track ID not found'], 400);
        }

        try {
            // Create associate album task
            $this->taskService->createTask(
                Task::TYPE_ASSOCIATE_ALBUM,
                (string) $releaseMbid,
                $unmatchedTrackId,
                (string) $albumTitle,
                [
                    'artist_name' => (string) $artistName,
                    'artist_mbid' => (string) $artistMbid,
                    'release_group_mbid' => null !== $releaseGroupMbid ? (string) $releaseGroupMbid : null,
                    'library_id' => $libraryId,
                ],
                3
            );

            $this->logger->info($this->translator->trans('api.info.album_association_task_created_log'), [
                'unmatched_track_id' => $unmatchedTrack->getId(),
                'album_title' => $albumTitle,
                'artist_name' => $artistName,
                'release_mbid' => $releaseMbid,
            ]);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.album_association_task_created', ['album_title' => $albumTitle]),
                'task_type' => 'album_association',
                'unmatched_track_id' => $unmatchedTrack->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.sync_task_creation_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.album_association_error')], 500);
        }
    }

    #[Route('/{id}/find-best-matches', name: 'unmatched_track_find_best_matches', methods: ['GET'])]
    public function findBestMatches(UnmatchedTrack $unmatchedTrack): JsonResponse
    {
        $artistName = $unmatchedTrack->getArtist();
        $title = $unmatchedTrack->getTitle();
        $albumTitle = $unmatchedTrack->getAlbum();
        $filePath = $unmatchedTrack->getFilePath();

        if (!$artistName || !$title) {
            return $this->json(['error' => $this->translator->trans('api.error.artist_name_and_title_required')], 400);
        }

        try {
            // Use AssociationStepChain to find the best matches
            // Prepare context for the association chain
            $context = [
                'dry_run' => true, // We're just finding matches, not actually associating
                'find_multiple_matches' => true, // Flag to indicate we want multiple matches
            ];

            // Execute the association chain to find potential matches
            $result = $this->associationStepChain->executeChain($unmatchedTrack, $context, $this->logger);

            // If we found a direct match through the chain, calculate its real score
            if ($result['track']) {
                $track = $result['track'];
                $trackId = $track->getId();
                $trackTitle = $track->getTitle();
                $trackAlbum = $track->getAlbum();
                // Log match details with score if available
                $track = $result['track'];
                $score = $result['score'] ?? null;
                $matchReason = $result['match_reason'] ?? null;

                $formattedMatches = [[
                    'id' => $trackId,
                    'title' => $trackTitle,
                    'album' => $trackAlbum,
                    'track_number' => $track->getTrackNumber(),
                    'score' => $score,
                    'reason' => $matchReason ?: 'Match found by association chain',
                ]];
            } else {
                // Fallback to the original matching logic for additional suggestions
                $formattedMatches = $this->findAdditionalMatches($unmatchedTrack, $artistName, $title, $albumTitle, $filePath);
            }

            return $this->json([
                'success' => true,
                'matches' => $formattedMatches,
                'unmatched_track' => [
                    'id' => $unmatchedTrack->getId(),
                    'title' => $unmatchedTrack->getTitle(),
                    'artist' => $unmatchedTrack->getArtist(),
                    'album' => $unmatchedTrack->getAlbum(),
                    'year' => $unmatchedTrack->getYear(),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error finding best matches: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error finding best matches',
            ], 500);
        }
    }

    /**
     * Find additional matches using the original logic as fallback.
     *
     * @return array<int, array{id: int, title: string, album: string, track_number: ?string, score: float, reason: string}>
     */
    private function findAdditionalMatches(UnmatchedTrack $unmatchedTrack, string $artistName, string $title, ?string $albumTitle, ?string $filePath): array
    {
        // Find artist in database
        $artist = $this->artistRepository->findByName($artistName);
        if (!$artist) {
            return [];
        }

        // Extract information from file path
        if (null === $filePath) {
            return [];
        }
        $pathInfo = $this->filePathAnalyzerService->extractPathInformation($filePath);

        // Find potential matches
        $matches = [];

        // Search by similar titles
        $artistId = $artist->getId();
        if (null === $artistId) {
            return [];
        }
        $similarTracks = $this->trackRepository->findSimilarTracksByArtist($artistId, $title);
        foreach ($similarTracks as $track) {
            $similarity = $this->stringSimilarityService->calculateSimilarity($title, $track->getTitle());
            if ($similarity > 0.7) { // 70% similarity threshold
                $score = $this->trackMatchingService->calculateMatchScore($track, $unmatchedTrack, $pathInfo);
                $matches[] = [
                    'track' => $track,
                    'score' => $score,
                    'reason' => $this->trackMatchingService->getMatchReason($track, $unmatchedTrack, $pathInfo),
                ];
            }
        }

        // Remove duplicates and sort by score
        $uniqueMatches = [];
        $seenTrackIds = [];
        foreach ($matches as $match) {
            $trackId = $match['track']->getId();
            if (!\in_array($trackId, $seenTrackIds, true)) {
                $uniqueMatches[] = $match;
                $seenTrackIds[] = $trackId;
            }
        }

        usort($uniqueMatches, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        // Format response
        $formattedMatches = [];
        foreach ($uniqueMatches as $match) {
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

        return $formattedMatches;
    }

    #[Route('/{id}/associate-to-track/{trackId}', name: 'unmatched_track_associate_to_track', methods: ['POST'])]
    public function associateToTrack(UnmatchedTrack $unmatchedTrack, int $trackId): JsonResponse
    {
        try {
            $track = $this->trackRepository->find($trackId);
            if (!$track) {
                return $this->json(['error' => 'Track not found'], 404);
            }

            // Check if the track already has a file
            if ($track->isHasFile()) {
                return $this->json(['error' => 'Track already has a file associated'], 400);
            }

            // Create a new TrackFile entity
            $trackFile = new TrackFile();
            $trackFile->setTrack($track);
            $filePath = $unmatchedTrack->getFilePath();
            if (null === $filePath) {
                throw new Exception('Unmatched track has no file path');
            }
            $trackFile->setFilePath($filePath);
            $trackFile->setFileSize($unmatchedTrack->getFileSize() ?? 0);
            $trackFile->setDuration($unmatchedTrack->getDuration() ?? 0);

            // Set format based on file extension
            $filePath = $unmatchedTrack->getFilePath();
            if ($filePath) {
                $extension = pathinfo($filePath, \PATHINFO_EXTENSION);
                if ($extension) {
                    $trackFile->setFormat(mb_strtoupper($extension));
                }
            }

            // Set lyrics path if available from unmatched track
            if ($unmatchedTrack->getLyricsFilepath()) {
                $trackFile->setLyricsPath($unmatchedTrack->getLyricsFilepath());
            }

            // Update the track to indicate it has a file
            $track->setHasFile(true);
            $track->setDownloaded(true);

            // Mark the unmatched track as matched
            $unmatchedTrack->setIsMatched(true);
            $unmatchedTrack->setLastAttemptedMatch(new DateTime());

            // Persist the changes
            $this->entityManager->persist($trackFile);
            $this->entityManager->persist($track);
            $this->entityManager->persist($unmatchedTrack);
            $this->entityManager->flush();

            $album = $track->getAlbum();
            $artist = $album?->getArtist();

            if (null === $album || null === $artist) {
                throw new Exception('Track is missing album or artist information');
            }

            return $this->json([
                'success' => true,
                'message' => 'Track successfully associated',
                'track_id' => $track->getId(),
                'track_title' => $track->getTitle(),
                'album_title' => $album->getTitle(),
                'artist_name' => $artist->getName(),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.track_association_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.track_association_error')], 500);
        }
    }

    /**
     * Calculate similarity between two strings using Levenshtein distance.
     */
    // Removed - now using StringSimilarity

    // Removed - now using FilePathAnalyzerService

    #[Route('/bulk-delete', name: 'unmatched_tracks_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        $ids = $request->request->get('ids');
        if (null === $ids) {
            return $this->json(['error' => 'No IDs provided'], 400);
        }

        if (!\is_array($ids)) {
            return $this->json(['error' => 'Invalid IDs format'], 400);
        }

        if (empty($ids)) {
            return $this->json(['error' => $this->translator->trans('api.error.no_tracks_selected')], 400);
        }

        try {
            $count = 0;
            foreach ($ids as $id) {
                $track = $this->unmatchedTrackRepository->find($id);
                if ($track) {
                    $this->unmatchedTrackRepository->remove($track);
                    ++$count;
                }
            }

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.tracks_deleted_successfully', ['count' => $count]),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.mass_deletion_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.deletion_error')], 500);
        }
    }

    #[Route('/{id}/delete', name: 'unmatched_track_delete', methods: ['DELETE'])]
    public function delete(UnmatchedTrack $unmatchedTrack): JsonResponse
    {
        try {
            $this->unmatchedTrackRepository->remove($unmatchedTrack, true);

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.track_deleted_successfully'),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.deletion_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.deletion_error')], 500);
        }
    }

    #[Route('/scan-libraries/execute', name: 'app_scan_libraries_execute', methods: ['POST'])]
    public function executeScan(Request $request): JsonResponse
    {
        try {
            /** @var array{libraryId?: int, dryRun?: bool, force?: bool} $data */
            $data = json_decode($request->getContent(), true) ?? [];
            $libraryId = $data['libraryId'] ?? null;
            $dryRun = $data['dryRun'] ?? true;
            $force = $data['force'] ?? false;

            if ($libraryId) {
                // Scanner une bibliothèque spécifique
                $library = $this->libraryRepository->find($libraryId);
                if (!$library) {
                    return $this->json(['error' => $this->translator->trans('api.error.library_not_found')], 404);
                }

                $this->taskService->createTask(
                    Task::TYPE_SCAN_LIBRARY,
                    null,
                    $libraryId,
                    $library->getName(),
                    ['dry_run' => $dryRun, 'force_analysis' => $force],
                    3
                );

                return $this->json([
                    'success' => true,
                    'message' => $this->translator->trans('api.success.library_scan_started', ['library_name' => $library->getName()]),
                ]);
            }
            // Scanner toutes les bibliothèques actives
            $libraries = $this->libraryRepository->findBy(['enabled' => true]);

            foreach ($libraries as $library) {
                $libraryId = $library->getId();
                if (null !== $libraryId) {
                    $this->taskService->createTask(
                        Task::TYPE_SCAN_LIBRARY,
                        null,
                        $libraryId,
                        $library->getName(),
                        ['dry_run' => $dryRun, 'force_analysis' => $force],
                        3
                    );
                }
            }

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.all_libraries_scan_started'),
                'libraries_count' => \count($libraries),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.scan_launch_error') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('api.error.scan_launch_error')], 500);
        }
    }
}
