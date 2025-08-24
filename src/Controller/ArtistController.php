<?php

declare(strict_types=1);

namespace App\Controller;

use App\Client\SpotifyWebApiClient;
use App\Configuration\Config\ConfigurationFactory;
use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Task;
use App\Manager\MediaImageManager;
use App\Manager\MusicLibraryManager;
use App\Repository\LibraryRepository;
use App\Service\ArtistAlbumGroupingService;
use App\Statistic\StatisticsService;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;
use Throwable;

#[Route('/artist')]
class ArtistController extends AbstractController
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private TaskFactory $taskService,
        private TranslatorInterface $translator,
        private MediaImageManager $mediaImageManager,
        private StatisticsService $statisticsService,
        private SpotifyWebApiClient $spotifyWebApiClient,
        private ConfigurationFactory $configurationFactory,
        private ArtistAlbumGroupingService $albumGroupingService,
        private LibraryRepository $libraryRepository,
    ) {
    }

    #[Route('/', name: 'artist_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get enabled libraries count
        $enabledLibrariesCount = $this->libraryRepository->count(['enabled' => true]);

        return $this->render('artist/index.html.twig', [
            'enabledLibrariesCount' => $enabledLibrariesCount,
        ]);
    }

    #[Route('/search', name: 'artist_search', methods: ['GET'])]
    public function search(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 50)));
        $query = $request->query->get('q', '');
        $libraryId = $request->query->get('library');
        $albumsFilter = (string) $request->query->get('albums', '');
        $statusFilter = (string) $request->query->get('status', '');

        // Convert library ID to int if provided
        $libraryIdInt = null;
        if ($libraryId && is_numeric($libraryId)) {
            $libraryIdInt = (int) $libraryId;
        }

        try {
            if (empty($query) || !\is_string($query)) {
                // Get all artists with pagination
                $artists = $this->musicLibraryManager->getAllArtistsPaginated($page, $limit, $libraryIdInt, $albumsFilter, $statusFilter);
                $totalCount = $this->musicLibraryManager->countAllArtists($libraryIdInt, $albumsFilter, $statusFilter);
            } else {
                // Search artists with pagination
                $artists = $this->musicLibraryManager->searchArtistsPaginated($query, $page, $limit, $libraryIdInt, $albumsFilter, $statusFilter);
                $totalCount = $this->musicLibraryManager->countSearchArtists($query, $libraryIdInt, $albumsFilter, $statusFilter);
            }

            $totalPages = ceil($totalCount / $limit);
            $hasNext = $page < $totalPages;

            $data = [];
            foreach ($artists as $artist) {
                $artistStats = $this->statisticsService->getArtistStatistics($artist);
                $albumCount = $artistStats ? ($artistStats['totalAlbums'] + $artistStats['totalSingles']) : $artist->getAlbums()->count();

                $tracksCount = $artistStats ? $artistStats['totalTracks'] : 0;
                $filesCount = $artistStats ? ($artistStats['downloadedTracks'] ?? 0) : 0;
                $data[] = [
                    'id' => $artist->getId(),
                    'name' => $artist->getName(),
                    'mbid' => $artist->getMbid(),
                    'country' => $artist->getCountry(),
                    'type' => $artist->getType(),
                    'status' => $artist->getStatus(),
                    'monitored' => $artist->isMonitored(),
                    'albumCount' => $albumCount,
                    'tracksCount' => $tracksCount,
                    'filesCount' => $filesCount,
                    'imageUrl' => $artist->getImageUrl(),
                    'hasArtistImage' => $artist->hasArtistImage(),
                    'artistImageUrl' => $artist->getArtistImageUrl(),
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'items' => $data,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalCount,
                        'totalPages' => $totalPages,
                        'hasNext' => $hasNext,
                        'hasPrev' => $page > 1,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in artist search: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error loading artists',
            ], 500);
        }
    }

    #[Route('/add', name: 'artist_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                return $this->json(['error' => $this->translator->trans('artist.invalid_json_data')], 400);
            }

            if (!\is_array($data)) {
                return $this->json(['error' => $this->translator->trans('artist.invalid_json_data')], 400);
            }

            $name = $data['name'] ?? '';
            $mbid = $data['mbid'] ?? null;

            if (empty($name)) {
                return $this->json(['error' => $this->translator->trans('artist.name_required')], 400);
            }

            $this->logger->info($this->translator->trans('artist.attempting_to_add', ['%name%' => $name, '%mbid%' => $mbid]));

            // Create task for syncing artist (create or update)
            $task = $this->taskService->createTask(
                Task::TYPE_SYNC_ARTIST,
                $mbid,
                null,
                $name,
                [],
                5
            );

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('artist.add_task_created'),
                'task_id' => $task->getId(),
                'task_type' => $task->getType(),
                'task_status' => $task->getStatus(),
                'artist_name' => $name,
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('artist.error_adding') . ': ' . $e->getMessage());

            return $this->json(['error' => $this->translator->trans('artist.server_error') . ': ' . $e->getMessage()], 500);
        }
    }

    /**
     * Affiche les détails d'un artiste.
     */
    #[Route('/{id}', name: 'artist_show')]
    public function show(Artist $artist, Request $request): Response
    {
        $statusFilter = (string) $request->query->get('status', '');
        $artistId = $artist->getId();
        if (null === $artistId) {
            throw new InvalidArgumentException('Artist ID is null');
        }

        $albums = $this->musicLibraryManager->getArtistAlbums($artistId);
        $filteredAlbums = $this->applyStatusFilter($albums, $statusFilter);

        $groupedAlbums = $this->albumGroupingService->groupAlbumsByReleaseGroupAndType($filteredAlbums);
        $availableStatuses = $this->albumGroupingService->getAvailableStatuses($albums);

        return $this->render('artist/show.html.twig', [
            'artist' => $artist,
            'albumsByType' => $groupedAlbums['albumsByType'],
            'albumsByReleaseGroup' => $groupedAlbums['albumsByReleaseGroup'],
            'statusFilter' => $statusFilter,
            'availableStatuses' => $availableStatuses,
        ]);
    }

    /**
     * Apply status filter to albums.
     */
    private function applyStatusFilter(array $albums, string $statusFilter): array
    {
        if (empty($statusFilter)) {
            return $albums;
        }

        return array_filter($albums, function (Album $album) use ($statusFilter) {
            return $album->getStatus() === $statusFilter;
        });
    }

    /**
     * Synchronise un artiste avec MusicBrainz.
     */
    #[Route('/{id}/sync', name: 'artist_sync', methods: ['POST'])]
    public function sync(Artist $artist): JsonResponse
    {
        try {
            $this->logger->info($this->translator->trans('api.log.sync_started_for_artist', ['artist_name' => $artist->getName()]));

            // Create task for syncing artist albums
            $artistId = $artist->getId();
            if (null === $artistId) {
                return $this->json(['error' => 'Artist ID is null'], 400);
            }

            // Create task for syncing artist (create or update)
            $task = $this->taskService->createTask(
                Task::TYPE_SYNC_ARTIST,
                $artist->getMbid(),
                null,
                $artist->getName(),
                ['artist_id' => $artist->getId()],
                5
            );

            $task = $this->taskService->createTask(
                Task::TYPE_SYNC_ARTIST_ALBUMS,
                null,
                $artistId,
                $artist->getName(),
                [],
                3
            );

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('api.info.sync_task_created'),
                'task_id' => $task->getId(),
                'task_type' => $task->getType(),
                'task_status' => $task->getStatus(),
                'artist_id' => $artist->getId(),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.sync_task_creation_error') . ': ' . $e->getMessage());

            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Synchronise tous les artistes avec MusicBrainz.
     */
    #[Route('/sync-all', name: 'artist_sync_all', methods: ['POST'])]
    public function syncAll(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!\is_array($data)) {
                return $this->json(['error' => 'Invalid JSON data'], 400);
            }

            $libraryId = isset($data['libraryId']) ? (int) $data['libraryId'] : null;

            $this->logger->info($this->translator->trans('api.log.sync_all_artists_started'), [
                'library_id' => $libraryId,
            ]);

            // Create task for syncing all artists
            $task = $this->taskService->createTask(
                Task::TYPE_SYNC_ALL_ARTISTS,
                null,
                $libraryId,
                null,
                [],
                3
            );

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('artist.sync_all_task_created'),
                'task_id' => $task->getId(),
                'task_type' => $task->getType(),
                'task_status' => $task->getStatus(),
                'library_id' => $libraryId,
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.sync_all_task_creation_error') . ': ' . $e->getMessage());

            return new JsonResponse([
                'success' => false,
                'error' => $this->translator->trans('artist.error_sync_all') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/update', name: 'artist_update', methods: ['POST'])]
    public function update(Artist $artist): JsonResponse
    {
        try {
            $this->musicLibraryManager->updateArtistInfo($artist);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/update-folder', name: 'artist_update_folder', methods: ['POST'])]
    public function updateFolder(Artist $artist, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!\is_array($data)) {
                return $this->json(['error' => 'Invalid JSON data'], 400);
            }

            $newFolderPath = $data['folder_path'] ?? null;
            $moveMetadata = $data['move_metadata'] ?? false;

            if (!$newFolderPath) {
                return $this->json([
                    'success' => false,
                    'error' => 'Folder path is required',
                ], 400);
            }

            // Validate the folder path
            if (!is_dir($newFolderPath)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid folder path: directory does not exist',
                ], 400);
            }

            $oldFolderPath = $artist->getArtistFolderPath();

            // Update the artist folder path
            $artist->setArtistFolderPath($newFolderPath);
            $this->entityManager->flush();

            $this->logger->info("Artist folder path updated for {$artist->getName()}: {$newFolderPath}");

            // Move artist image if requested and metadata is saved in library folders
            if ($moveMetadata) {
                $metadataConfig = $this->configurationFactory->getDefaultConfiguration('metadata.');
                if ($metadataConfig['save_in_library'] ?? false) {
                    $this->mediaImageManager->moveArtistImage($artist, $oldFolderPath, $newFolderPath);
                    $this->entityManager->flush();
                }
            }

            return $this->json([
                'success' => true,
                'message' => 'Artist folder path updated successfully',
                'folder_path' => $newFolderPath,
            ]);
        } catch (Exception $e) {
            $this->logger->error("Error updating artist folder path for {$artist->getName()}: " . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error updating artist folder path',
            ], 500);
        }
    }

    #[Route('/{id}/refresh-image', name: 'artist_refresh_image', methods: ['POST'])]
    public function refreshImage(Artist $artist): JsonResponse
    {
        // Ensure Spotify ID is set (search by name if missing)
        if (!$artist->getSpotifyId()) {
            try {
                $artistName = $artist->getName();
                if ($artistName) {
                    $spotify = $this->spotifyWebApiClient->searchArtist($artistName);
                    if ($spotify && ($spotify['id'] ?? null)) {
                        $artist->setSpotifyId($spotify['id']);
                    }
                    $this->entityManager->flush();
                }
            } catch (Throwable $e) {
                $this->logger->warning('Spotify enrichment during update failed for ' . ($artist->getName() ?? 'unknown') . ': ' . $e->getMessage());
            }
        }

        try {
            // Use the MediaImageManager to download and store the artist image
            $artistName = $artist->getName();
            if (!$artistName) {
                return $this->json([
                    'success' => false,
                    'error' => 'Artist name is required',
                ], 400);
            }

            $imagePath = $this->mediaImageManager->downloadAndStoreArtistImage(
                $artistName,
                $artist->getMbid() ?: (string) $artist->getId(),
                $artist->getMbid(),
                true,
                $artist->getSpotifyId(),
                $artist->getArtistFolderPath(),
                $artist->getId()
            );

            if ($imagePath) {
                // Update the artist's image URL
                $artist->setImageUrl($imagePath);
                $this->entityManager->flush();

                // Get detailed image information
                $imageInfo = $artist->getArtistImageInfo();

                $this->logger->info("Artist image refreshed for {$artist->getName()}: {$imagePath}");

                return $this->json([
                    'success' => true,
                    'message' => $this->translator->trans('artist.image_refreshed', [], 'messages', 'en'),
                    'image_url' => $imagePath,
                    'image_info' => $imageInfo,
                ]);
            }

            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('artist.no_image_found', [], 'messages', 'en'),
            ], 404);
        } catch (Exception $e) {
            $this->logger->error("Error refreshing artist image for {$artist->getName()}: " . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('artist.error_refreshing_image', [], 'messages', 'en'),
            ], 500);
        }
    }

    #[Route('/{id}/image-info', name: 'artist_image_info', methods: ['GET'])]
    public function getImageInfo(Artist $artist): JsonResponse
    {
        try {
            $imageInfo = $artist->getArtistImageInfo();

            if (!$imageInfo) {
                return $this->json([
                    'success' => false,
                    'error' => 'No image found for this artist',
                ], 404);
            }

            return $this->json([
                'success' => true,
                'data' => $imageInfo,
            ]);
        } catch (Exception $e) {
            $this->logger->error("Error getting image info for artist {$artist->getName()}: " . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error retrieving image information',
            ], 500);
        }
    }

    #[Route('/{id}/validate-image', name: 'artist_validate_image', methods: ['POST'])]
    public function validateImage(Artist $artist): JsonResponse
    {
        try {
            $isValid = $artist->isArtistImageValid();
            $imageInfo = $artist->getArtistImageInfo();

            return $this->json([
                'success' => true,
                'data' => [
                    'is_valid' => $isValid,
                    'image_info' => $imageInfo,
                    'validation_errors' => $this->getImageValidationErrors($artist),
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error("Error validating image for artist {$artist->getName()}: " . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error validating image',
            ], 500);
        }
    }

    /**
     * Get detailed validation errors for artist image.
     */
    private function getImageValidationErrors(Artist $artist): array
    {
        $errors = [];

        if (!$artist->hasArtistImage()) {
            $errors[] = 'No image found';

            return $errors;
        }

        $imageInfo = $artist->getArtistImageInfo();
        if (!$imageInfo) {
            $errors[] = 'Unable to read image information';

            return $errors;
        }

        // Check file size
        if ($imageInfo['file_size'] > 10 * 1024 * 1024) {
            $errors[] = 'File size exceeds 10MB limit';
        }

        // Check dimensions
        if ($imageInfo['width'] > 2000 || $imageInfo['height'] > 2000) {
            $errors[] = 'Image dimensions are too large (max 2000x2000)';
        }

        // Check MIME type
        $allowedMimeTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        if (!\in_array($imageInfo['mime_type'], $allowedMimeTypes, true)) {
            $errors[] = 'Unsupported image format';
        }

        return $errors;
    }

    #[Route('/{id}/toggle-monitor', name: 'artist_toggle_monitor', methods: ['POST'])]
    public function toggleMonitor(Artist $artist): JsonResponse
    {
        $artist->setMonitored(!$artist->isMonitored());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'monitored' => $artist->isMonitored(),
        ]);
    }

    #[Route('/{id}/albums', name: 'artist_albums', methods: ['GET'])]
    public function albums(Artist $artist): JsonResponse
    {
        $artistId = $artist->getId();
        if (null === $artistId) {
            return $this->json(['error' => 'Artist ID is null'], 400);
        }
        $albums = $this->musicLibraryManager->getArtistAlbums($artistId);

        $data = [];
        foreach ($albums as $album) {
            $albumStats = $this->statisticsService->getAlbumStatistics($album);
            $trackCount = $albumStats ? $albumStats['totalTracks'] : $album->getTracks()->count();

            $data[] = [
                'id' => $album->getId(),
                'title' => $album->getTitle(),
                'mbid' => $album->getMbid(),
                'releaseDate' => $album->getReleaseDate()?->format('Y-m-d'),
                'status' => $album->getStatus(),
                'albumType' => $album->getAlbumType(),
                'monitored' => $album->isMonitored(),
                'downloaded' => $album->isDownloaded(),
                'trackCount' => $trackCount,
            ];
        }

        return $this->json($data);
    }

    #[Route('/{id}/albums/filtered', name: 'artist_albums_filtered', methods: ['GET'])]
    public function albumsFiltered(Artist $artist, Request $request): JsonResponse
    {
        $statusFilter = $request->query->get('status', '');
        $albumType = $request->query->get('type', '');

        $artistId = $artist->getId();
        if (null === $artistId) {
            return $this->json(['error' => 'Artist ID is null'], 400);
        }
        $albums = $this->musicLibraryManager->getArtistAlbums($artistId);

        // Apply status filter
        if (!empty($statusFilter)) {
            $albums = array_filter($albums, function ($album) use ($statusFilter) {
                return $album->getStatus() === $statusFilter;
            });
        }

        // Apply album type filter
        if (!empty($albumType)) {
            $albums = array_filter($albums, function ($album) use ($albumType) {
                return $album->getAlbumType() === $albumType;
            });
        }

        $data = [];
        foreach ($albums as $album) {
            $albumStats = $this->statisticsService->getAlbumStatistics($album);
            $trackCount = $albumStats ? $albumStats['totalTracks'] : $album->getTracks()->count();

            $data[] = [
                'id' => $album->getId(),
                'title' => $album->getTitle(),
                'mbid' => $album->getMbid(),
                'releaseDate' => $album->getReleaseDate()?->format('Y-m-d'),
                'status' => $album->getStatus(),
                'albumType' => $album->getAlbumType(),
                'monitored' => $album->isMonitored(),
                'downloaded' => $album->isDownloaded(),
                'trackCount' => $trackCount,
                'hasCoverImage' => $album->hasCoverImage(),
                'coverImageUrl' => $album->getCoverImageUrl(),
            ];
        }

        return $this->json($data);
    }

    /**
     * Test releases for a specific release group from MusicBrainz.
     */
    #[Route('/{id}/test-releases', name: 'artist_test_releases', methods: ['GET'])]
    public function testReleases(Artist $artist, Request $request): JsonResponse
    {
        try {
            if (!$artist->getMbid()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Artist has no MusicBrainz ID',
                ], 400);
            }

            $releaseGroupId = $request->query->get('releaseGroupId');
            if (!$releaseGroupId || !\is_string($releaseGroupId)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Release group ID is required',
                ], 400);
            }

            // Test the API call directly
            $apiClient = $this->musicLibraryManager->getMusicBrainzApiClient();

            // Get releases for the specified release group
            $releases = $apiClient->getReleasesByReleaseGroup($releaseGroupId);

            return $this->json([
                'success' => true,
                'releases_count' => \is_array($releases) ? \count($releases) : 0,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error testing releases: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());

            return $this->json([
                'success' => false,
                'error' => 'Error testing releases: ' . $e->getMessage(),
                'stack_trace' => $e->getTraceAsString(),
            ], 500);
        }
    }

    /**
     * Get release groups for an artist from MusicBrainz.
     */
    #[Route('/{id}/release-groups', name: 'artist_release_groups', methods: ['GET'])]
    public function getReleaseGroups(Artist $artist, Request $request): JsonResponse
    {
        try {
            if (!$artist->getMbid()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Artist has no MusicBrainz ID',
                ], 400);
            }

            $searchTerm = $request->query->get('search', '');

            // Get all release groups for the artist
            $releaseGroups = $this->musicLibraryManager->getMusicBrainzApiClient()->getArtistReleaseGroups($artist->getMbid());

            // Log the raw response for debugging
            $this->logger->info('Raw release groups response for artist ' . $artist->getMbid() . ': ' . json_encode([
                'count' => \is_array($releaseGroups) ? \count($releaseGroups) : 'not_array',
                'type' => \gettype($releaseGroups),
                'sample' => \is_array($releaseGroups) && !empty($releaseGroups) ? \array_slice($releaseGroups, 0, 2) : 'no_sample',
            ]));

            // Ensure we have an array
            if (!\is_array($releaseGroups)) {
                $this->logger->error('Release groups is not an array: ' . \gettype($releaseGroups));
                $releaseGroups = [];
            }

            // Filter by search term if provided
            if (!empty($searchTerm) && \is_string($searchTerm)) {
                $releaseGroups = array_filter($releaseGroups, function ($group) use ($searchTerm) {
                    if (!\is_array($group)) {
                        return false;
                    }

                    $title = $group['title'] ?? '';
                    $firstReleaseDate = $group['first-release-date'] ?? '';
                    $primaryType = $group['primary-type'] ?? '';

                    return false !== mb_stripos($title, $searchTerm)
                           || false !== mb_stripos($firstReleaseDate, $searchTerm)
                           || false !== mb_stripos($primaryType, $searchTerm);
                });
            }

            return $this->json([
                'success' => true,
                'releaseGroups' => array_values($releaseGroups),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error fetching release groups: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch release groups',
            ], 500);
        }
    }

    /**
     * Get releases for a specific release group from MusicBrainz.
     */
    #[Route('/{id}/releases', name: 'artist_releases', methods: ['GET'])]
    public function getReleases(Artist $artist, Request $request): JsonResponse
    {
        try {
            if (!$artist->getMbid()) {
                return $this->json([
                    'success' => false,
                    'error' => 'Artist has no MusicBrainz ID',
                ], 400);
            }

            $releaseGroupId = $request->query->get('releaseGroupId');
            if (!$releaseGroupId || !\is_string($releaseGroupId)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Release group ID is required',
                ], 400);
            }

            $this->logger->info('Fetching releases for release group: ' . $releaseGroupId . ' (Artist: ' . $artist->getMbid() . ')');

            // Get releases for the specified release group
            $releases = $this->musicLibraryManager->getMusicBrainzApiClient()->getReleasesByReleaseGroup($releaseGroupId);

            // Log the raw response for debugging
            $this->logger->info('Raw releases response for release group ' . $releaseGroupId . ': ' . json_encode([
                'count' => \is_array($releases) ? \count($releases) : 'not_array',
                'type' => \gettype($releases),
                'sample' => \is_array($releases) && !empty($releases) ? \array_slice($releases, 0, 2) : 'no_sample',
            ]));

            // Ensure we have an array
            if (!\is_array($releases)) {
                $this->logger->error('Releases is not an array: ' . \gettype($releases));
                $releases = [];
            }

            return $this->json([
                'success' => true,
                'releases' => array_values($releases),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error fetching releases: ' . $e->getMessage());
            $this->logger->error('Stack trace: ' . $e->getTraceAsString());

            return $this->json([
                'success' => false,
                'error' => 'Failed to fetch releases: ' . $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Add a new release to an artist.
     */
    #[Route('/{id}/add-release', name: 'artist_add_release', methods: ['POST'])]
    public function addRelease(Artist $artist, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (\JSON_ERROR_NONE !== json_last_error()) {
                return $this->json(['error' => 'Invalid JSON data'], 400);
            }

            if (!\is_array($data)) {
                return $this->json(['error' => 'Invalid JSON data'], 400);
            }

            $releaseGroupId = $data['releaseGroupId'] ?? null;
            $releaseId = $data['releaseId'] ?? null;
            $releaseTitle = $data['releaseTitle'] ?? '';
            $releaseGroupTitle = $data['releaseGroupTitle'] ?? '';

            if (!$releaseGroupId || !$releaseId || empty($releaseTitle)) {
                return $this->json(['error' => 'Release group ID, release ID, and release title are required'], 400);
            }

            $this->logger->info('Adding release to artist', [
                'artist_id' => $artist->getId(),
                'artist_name' => $artist->getName(),
                'release_group_id' => $releaseGroupId,
                'release_id' => $releaseId,
                'release_title' => $releaseTitle,
                'release_group_title' => $releaseGroupTitle,
            ]);

            // Create task for adding the album
            $task = $this->taskService->createTask(
                Task::TYPE_ADD_ALBUM,
                $releaseId,
                null,
                $releaseTitle,
                [
                    'artist_id' => $artist->getId(),
                    'artist_name' => $artist->getName(),
                    'release_group_id' => $releaseGroupId,
                    'release_group_title' => $releaseGroupTitle,
                ],
                3
            );

            return $this->json([
                'success' => true,
                'message' => 'Release addition started successfully. The processing will happen in the background.',
                'task_id' => $task->getId(),
                'task_type' => $task->getType(),
                'task_status' => $task->getStatus(),
                'release_title' => $releaseTitle,
                'artist_name' => $artist->getName(),
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error adding release: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error adding release: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'artist_delete', methods: ['DELETE'])]
    public function delete(Artist $artist): JsonResponse
    {
        try {
            $artistName = $artist->getName();

            // Supprimer l'artiste et tous ses albums/tracks associés
            $this->musicLibraryManager->deleteArtist($artist);

            $this->logger->info($this->translator->trans('artist.deleted_successfully_log', ['%name%' => $artistName]));

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('artist.deleted_success', ['%name%' => $artistName]),
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('artist.error_deleting_log') . ': ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('artist.error_deleting'),
            ], 500);
        }
    }

    #[Route('/test-musicbrainz', name: 'test_musicbrainz', methods: ['GET'])]
    public function testMusicBrainz(): JsonResponse
    {
        return $this->json([
            'success' => true,
            'message' => 'Test endpoint working',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    #[Route('/search-musicbrainz', name: 'search_musicbrainz', methods: ['GET'])]
    public function searchMusicBrainz(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');

            if (empty($query) || !\is_string($query)) {
                return $this->json(['error' => $this->translator->trans('api.error.search_term_required')], 400);
            }

            $this->logger->info($this->translator->trans('artist.musicbrainz_search', ['%query%' => $query]));

            $artists = $this->musicLibraryManager->getMusicBrainzApiClient()->searchArtist($query);

            $this->logger->info($this->translator->trans('artist.artists_found_count', ['%count%' => \count($artists)]));

            $data = [];
            foreach ($artists as $artist) {
                $data[] = [
                    'id' => $artist['id'],
                    'name' => $artist['name'],
                    'country' => $artist['country'] ?? null,
                    'type' => $artist['type'] ?? null,
                    'disambiguation' => $artist['disambiguation'] ?? null,
                    'life_span' => $artist['life-span'] ?? null,
                ];
            }

            return $this->json([
                'success' => true,
                'artists' => $data,
            ]);
        } catch (Exception $e) {
            $this->logger->error($this->translator->trans('api.log.musicbrainz_search_error') . ': ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/paginated', name: 'artist_paginated', methods: ['GET'])]
    public function paginated(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 50))); // Between 10 and 100
        $query = $request->query->get('search', '');
        $libraryId = $request->query->get('library');
        $albumsFilter = (string) $request->query->get('albums', '');
        $statusFilter = (string) $request->query->get('status', '');

        // Convert library ID to int if provided
        $libraryIdInt = null;
        if ($libraryId && is_numeric($libraryId)) {
            $libraryIdInt = (int) $libraryId;
        }

        try {
            $this->logger->info('Artist paginated request', [
                'page' => $page,
                'limit' => $limit,
                'query' => $query,
                'libraryId' => $libraryIdInt,
                'albumsFilter' => $albumsFilter,
                'statusFilter' => $statusFilter,
            ]);

            if (empty($query) || !\is_string($query)) {
                // Get all artists with pagination
                $artists = $this->musicLibraryManager->getAllArtistsPaginated($page, $limit, $libraryIdInt, $albumsFilter, $statusFilter);
                $totalCount = $this->musicLibraryManager->countAllArtists($libraryIdInt, $albumsFilter, $statusFilter);
            } else {
                // Search artists with pagination
                $artists = $this->musicLibraryManager->searchArtistsPaginated($query, $page, $limit, $libraryIdInt, $albumsFilter, $statusFilter);
                $totalCount = $this->musicLibraryManager->countSearchArtists($query, $libraryIdInt, $albumsFilter, $statusFilter);
            }

            $totalPages = ceil($totalCount / $limit);
            $hasNext = $page < $totalPages;

            $data = [];
            foreach ($artists as $artist) {
                $artistStats = $this->statisticsService->getArtistStatistics($artist);
                $albumCount = $artistStats ? ($artistStats['totalAlbums'] + $artistStats['totalSingles']) : $artist->getAlbums()->count();

                $tracksCount = $artistStats ? $artistStats['totalTracks'] : 0;
                $filesCount = $artistStats ? ($artistStats['downloadedTracks'] ?? 0) : 0;
                $data[] = [
                    'id' => $artist->getId(),
                    'name' => $artist->getName(),
                    'mbid' => $artist->getMbid(),
                    'country' => $artist->getCountry(),
                    'type' => $artist->getType(),
                    'status' => $artist->getStatus(),
                    'monitored' => $artist->isMonitored(),
                    'albumCount' => $albumCount,
                    'tracksCount' => $tracksCount,
                    'filesCount' => $filesCount,
                    'imageUrl' => $artist->getImageUrl(),
                    'hasArtistImage' => $artist->hasArtistImage(),
                    'artistImageUrl' => $artist->getArtistImageUrl(),
                ];
            }

            return $this->json([
                'success' => true,
                'data' => [
                    'items' => $data,
                    'pagination' => [
                        'page' => $page,
                        'limit' => $limit,
                        'total' => $totalCount,
                        'totalPages' => $totalPages,
                        'hasNext' => $hasNext,
                        'hasPrev' => $page > 1,
                    ],
                ],
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error in artist pagination: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error loading artists',
            ], 500);
        }
    }

    /**
     * Search for releases by query string using MusicBrainz API.
     */
    #[Route('/search-releases', name: 'artist_search_releases', methods: ['GET'])]
    public function searchReleases(Request $request): JsonResponse
    {
        try {
            $query = $request->query->get('q', '');
            $artistMbid = $request->query->get('artist_mbid', '');

            if (empty($query) || !\is_string($query)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Query parameter is required',
                ], 400);
            }

            if (empty($artistMbid) || !\is_string($artistMbid)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Artist MBID parameter is required',
                ], 400);
            }

            $this->logger->info('Searching releases for artist', [
                'query' => $query,
                'artist_mbid' => $artistMbid,
            ]);

            // Get all releases for the artist
            $allReleases = $this->musicLibraryManager->getMusicBrainzApiClient()->getArtistReleases($artistMbid);

            // Filter releases by query (case-insensitive search in title)
            $filteredReleases = array_filter($allReleases, function ($release) use ($query) {
                if (!\is_array($release)) {
                    return false;
                }
                $title = $release['title'] ?? '';

                return false !== mb_stripos($title, $query);
            });

            // Limit results to first 20 matches
            $filteredReleases = \array_slice($filteredReleases, 0, 20);

            // Format the response
            $formattedReleases = array_map(function ($release) {
                $releaseGroup = $release['release-group'] ?? [];

                return [
                    'id' => $release['id'] ?? '',
                    'title' => $release['title'] ?? '',
                    'date' => $release['date'] ?? null,
                    'type' => $releaseGroup['primary-type'] ?? null,
                    'country' => $release['country'] ?? null,
                    'releaseGroupId' => $releaseGroup['id'] ?? '',
                    'releaseGroupTitle' => $releaseGroup['title'] ?? '',
                ];
            }, $filteredReleases);

            return $this->json([
                'success' => true,
                'releases' => $formattedReleases,
            ]);
        } catch (Exception $e) {
            $this->logger->error('Error searching releases: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Failed to search releases: ' . $e->getMessage(),
            ], 500);
        }
    }
}
