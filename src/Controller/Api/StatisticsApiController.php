<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Entity\Album;
use App\Entity\Artist;
use App\Entity\Library;
use App\Statistic\StatisticsService;
use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/statistics')]
class StatisticsApiController extends AbstractController
{
    public function __construct(
        private StatisticsService $statisticsService
    ) {
    }

    #[Route('/summary', name: 'api_statistics_summary', methods: ['GET'])]
    public function summary(): JsonResponse
    {
        try {
            $summary = $this->statisticsService->getStatisticsSummary();

            return new JsonResponse([
                'success' => true,
                'data' => $summary,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get statistics summary: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/libraries', name: 'api_statistics_libraries', methods: ['GET'])]
    public function libraries(): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getAllLibraryStatistics();

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get library statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/library/{id}', name: 'api_statistics_library', methods: ['GET'])]
    public function library(Library $library): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getLibraryStatistics($library);

            if (!$statistics) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No statistics found for this library',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get library statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/artists/library/{libraryId}', name: 'api_statistics_artists_by_library', methods: ['GET'])]
    public function artistsByLibrary(int $libraryId): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getArtistStatisticsByLibrary($libraryId);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get artist statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/artist/{id}', name: 'api_statistics_artist', methods: ['GET'])]
    public function artist(Artist $artist): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getArtistStatistics($artist);

            if (!$statistics) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No statistics found for this artist',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get artist statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/albums/artist/{artistId}', name: 'api_statistics_albums_by_artist', methods: ['GET'])]
    public function albumsByArtist(int $artistId): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getAlbumStatisticsByArtist($artistId);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get album statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/albums/library/{libraryId}', name: 'api_statistics_albums_by_library', methods: ['GET'])]
    public function albumsByLibrary(int $libraryId): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getAlbumStatisticsByLibrary($libraryId);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get album statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/album/{id}', name: 'api_statistics_album', methods: ['GET'])]
    public function album(Album $album): JsonResponse
    {
        try {
            $statistics = $this->statisticsService->getAlbumStatistics($album);

            if (!$statistics) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'No statistics found for this album',
                ], 404);
            }

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get album statistics: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/top/artists/albums', name: 'api_statistics_top_artists_albums', methods: ['GET'])]
    public function topArtistsByAlbums(Request $request): JsonResponse
    {
        try {
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
            $statistics = $this->statisticsService->getTopArtistsByAlbums($limit);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
                'limit' => $limit,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get top artists by albums: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/top/artists/tracks', name: 'api_statistics_top_artists_tracks', methods: ['GET'])]
    public function topArtistsByTracks(Request $request): JsonResponse
    {
        try {
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
            $statistics = $this->statisticsService->getTopArtistsByTracks($limit);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
                'limit' => $limit,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get top artists by tracks: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/top/albums/complete', name: 'api_statistics_most_complete_albums', methods: ['GET'])]
    public function mostCompleteAlbums(Request $request): JsonResponse
    {
        try {
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
            $statistics = $this->statisticsService->getMostCompleteAlbums($limit);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
                'limit' => $limit,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get most complete albums: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/top/albums/incomplete', name: 'api_statistics_least_complete_albums', methods: ['GET'])]
    public function leastCompleteAlbums(Request $request): JsonResponse
    {
        try {
            $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
            $statistics = $this->statisticsService->getLeastCompleteAlbums($limit);

            return new JsonResponse([
                'success' => true,
                'data' => $statistics,
                'count' => \count($statistics),
                'limit' => $limit,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get least complete albums: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/counts', name: 'api_statistics_counts', methods: ['GET'])]
    public function counts(): JsonResponse
    {
        try {
            $counts = $this->statisticsService->getStatisticsCounts();

            return new JsonResponse([
                'success' => true,
                'data' => $counts,
            ]);
        } catch (Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => 'Failed to get statistics counts: ' . $e->getMessage(),
            ], 500);
        }
    }
}
