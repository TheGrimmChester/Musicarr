<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Library;
use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\LibraryRepository;
use App\Statistic\StatisticsService;
use App\Task\TaskFactory;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/library')]
class LibraryController extends AbstractController
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private TaskFactory $taskService,
        private LibraryRepository $libraryRepository,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
        private LoggerInterface $logger,
        private StatisticsService $statisticsService,
    ) {
    }

    #[Route('/', name: 'library_index', methods: ['GET'])]
    public function index(): Response
    {
        $libraries = $this->entityManager
            ->getRepository(Library::class)
            ->findAll();

        return $this->render('library/index.html.twig', [
            'libraries' => $libraries,
        ]);
    }

    #[Route('/browse-directories', name: 'library_browse_directories', methods: ['GET'])]
    public function browseDirectories(Request $request): JsonResponse
    {
        $path = $request->query->get('path', '');
        $listRoots = $request->query->get('listRoots', false);

        // Type validation for path
        if (!\is_string($path)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid path parameter',
            ], 400);
        }

        // If listing roots, return all available root directories
        if ($listRoots) {
            return $this->json([
                'success' => true,
                'roots' => $this->getAvailableRoots(),
            ]);
        }

        // If no path provided, return roots
        if (empty($path)) {
            return $this->json([
                'success' => true,
                'roots' => $this->getAvailableRoots(),
            ]);
        }

        // Additional security: prevent directory traversal attacks
        $realPath = realpath($path);
        if (false === $realPath || !is_dir($realPath)) {
            return $this->json([
                'success' => false,
                'error' => 'Invalid directory path',
            ], 400);
        }

        // Ensure the path is within allowed directories
        if (!$this->isPathAllowed($realPath)) {
            return $this->json([
                'success' => false,
                'error' => 'Access denied to this directory',
            ], 403);
        }

        try {
            $directories = $this->getDirectories($realPath);

            return $this->json([
                'success' => true,
                'path' => $realPath,
                'directories' => $directories,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * @return array<int, array{name: string, path: string, displayName: string, hasChildren: bool}>
     */
    private function getAvailableRoots(): array
    {
        $roots = [];
        $allowedRoots = ['/home', '/media', '/mnt', '/opt', '/var', '/tmp', '/usr/local', '/usr/share'];

        foreach ($allowedRoots as $root) {
            if (is_dir($root) && is_readable($root)) {
                $roots[] = [
                    'name' => basename($root),
                    'path' => $root,
                    'displayName' => $this->getRootDisplayName($root),
                    'hasChildren' => $this->hasSubdirectories($root),
                ];
            }
        }

        // Add user's home directory
        $userHome = getenv('HOME') ?: '/home/' . get_current_user();
        if (is_dir($userHome) && is_readable($userHome)) {
            $roots[] = [
                'name' => basename($userHome),
                'path' => $userHome,
                'displayName' => 'Home (' . basename($userHome) . ')',
                'hasChildren' => $this->hasSubdirectories($userHome),
            ];
        }

        return $roots;
    }

    private function getRootDisplayName(string $path): string
    {
        $displayNames = [
            '/home' => 'Home Directories',
            '/media' => 'Media Devices',
            '/mnt' => 'Mounted Devices',
            '/opt' => 'Optional Software',
            '/var' => 'Variable Data',
            '/tmp' => 'Temporary Files',
            '/usr/local' => 'Local Software',
            '/usr/share' => 'Shared Data',
        ];

        return $displayNames[$path] ?? basename($path);
    }

    private function isPathAllowed(string $path): bool
    {
        $allowedPaths = ['/home', '/media', '/mnt', '/opt', '/var', '/tmp', '/usr/local', '/usr/share'];

        foreach ($allowedPaths as $allowedPath) {
            if (str_starts_with($path, $allowedPath)) {
                return true;
            }
        }

        // Allow access to user's home directory and common music directories
        $userHome = getenv('HOME') ?: '/home/' . get_current_user();
        if (str_starts_with($path, $userHome)) {
            return true;
        }

        // Allow access to common music directories
        $musicPaths = [
            $userHome . '/Music',
            $userHome . '/Downloads',
            $userHome . '/Documents',
            '/media',
            '/mnt',
        ];
        foreach ($musicPaths as $musicPath) {
            if (str_starts_with($path, $musicPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array{name: string, path: string, hasChildren: bool}>
     */
    private function getDirectories(string $path): array
    {
        if (!is_dir($path)) {
            throw new Exception('Directory not found');
        }

        $directories = [];
        $items = scandir($path);

        if (false === $items) {
            throw new Exception('Unable to scan directory');
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $fullPath = $path . '/' . $item;
            if (is_dir($fullPath) && is_readable($fullPath)) {
                $directories[] = [
                    'name' => $item,
                    'path' => $fullPath,
                    'hasChildren' => $this->hasSubdirectories($fullPath),
                ];
            }
        }

        // Sort directories alphabetically
        usort($directories, function ($a, $b) {
            return strcasecmp($a['name'], $b['name']);
        });

        return $directories;
    }

    private function hasSubdirectories(string $path): bool
    {
        if (!is_dir($path) || !is_readable($path)) {
            return false;
        }

        $items = scandir($path);
        if (false === $items) {
            return false;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            if (is_dir($path . '/' . $item)) {
                return true;
            }
        }

        return false;
    }

    #[Route('/list', name: 'library_list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $libraries = $this->entityManager
            ->getRepository(Library::class)
            ->findAll();

        $data = [];
        foreach ($libraries as $library) {
            $libraryId = $library->getId();
            if (null === $libraryId) {
                continue; // Skip libraries without ID
            }

            $stats = $this->statisticsService->getLibraryStatistics($library);
            $data[] = [
                'id' => $libraryId,
                'name' => $library->getName(),
                'path' => $library->getPath(),
                'enabled' => $library->isEnabled(),
                'scanAutomatically' => $library->isScanAutomatically(),
                'scanInterval' => $library->getScanInterval(),
                'lastScan' => $library->getLastScan()->format('Y-m-d H:i:s'),
                'stats' => $stats ?? [
                    'totalArtists' => 0,
                    'totalAlbums' => 0,
                    'totalTracks' => 0,
                    'downloadedAlbums' => 0,
                    'downloadedTracks' => 0,
                    'totalSingles' => 0,
                    'downloadedSingles' => 0,
                ],
            ];
        }

        return $this->json($data);
    }

    #[Route('/add', name: 'library_add', methods: ['POST'])]
    public function add(Request $request): JsonResponse
    {
        /** @var array{name?: string, path?: string, enabled?: bool, scanAutomatically?: bool, scanInterval?: int} $data */
        $data = json_decode($request->getContent(), true);

        // Validate required fields
        if (empty($data['name'])) {
            return $this->json(['error' => 'Library name is required'], 400);
        }

        if (empty($data['path'])) {
            return $this->json(['error' => 'Library path is required'], 400);
        }

        $library = new Library();
        $library->setName($data['name']);
        $library->setPath($data['path']);
        $library->setEnabled($data['enabled'] ?? true);
        $library->setScanAutomatically($data['scanAutomatically'] ?? true);
        $library->setScanInterval($data['scanInterval'] ?? 60);
        $library->setMonitorNewItems(true);
        $library->setMonitorExistingItems(true);

        $this->entityManager->persist($library);
        $this->entityManager->flush();

        return $this->json(['success' => true, 'id' => $library->getId()]);
    }

    #[Route('/{id}', name: 'library_show', methods: ['GET'])]
    public function show(Library $library): Response
    {
        $libraryId = $library->getId();
        if (null === $libraryId) {
            throw new Exception('Library has no ID');
        }

        $stats = $this->statisticsService->getLibraryStatistics($library);

        // Provide default values if stats is null
        if (null === $stats) {
            $stats = [
                'totalAlbums' => 0,
                'totalTracks' => 0,
                'downloadedAlbums' => 0,
                'downloadedTracks' => 0,
                'totalSingles' => 0,
                'downloadedSingles' => 0,
            ];
        }

        return $this->render('library/show.html.twig', [
            'library' => $library,
            'stats' => $stats,
        ]);
    }

    #[Route('/{id}/scan', name: 'library_scan', methods: ['POST'])]
    public function scan(Library $library, Request $request): JsonResponse
    {
        try {
            $libraryId = $library->getId();
            if (null === $libraryId) {
                return $this->json(['error' => 'Library has no ID'], 400);
            }

            /** @var array{dryRun?: bool, force?: bool} $data */
            $data = json_decode($request->getContent(), true) ?? [];
            $dryRun = $data['dryRun'] ?? false;
            $force = $data['force'] ?? false;

            // Envoyer le message asynchrone
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
                'message' => $this->translator->trans('api.success.scan_started_background'),
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/toggle', name: 'library_toggle', methods: ['POST'])]
    public function toggle(Library $library, Request $request): JsonResponse
    {
        /** @var array{enabled?: bool} $data */
        $data = json_decode($request->getContent(), true);
        $enabled = $data['enabled'] ?? false;

        $library->setEnabled($enabled);

        $this->entityManager->flush();

        return $this->json(['success' => true, 'enabled' => $enabled]);
    }

    #[Route('/{id}/stats', name: 'library_stats', methods: ['GET'])]
    public function stats(Library $library): JsonResponse
    {
        $libraryId = $library->getId();
        if (null === $libraryId) {
            return $this->json(['error' => 'Library has no ID'], 400);
        }

        $stats = $this->statisticsService->getLibraryStatistics($library);

        return $this->json($stats);
    }

    #[Route('/scan-all', name: 'library_scan_all', methods: ['POST'])]
    public function scanAll(Request $request): JsonResponse
    {
        try {
            /** @var array{dryRun?: bool, force?: bool} $data */
            $data = json_decode($request->getContent(), true) ?? [];
            $dryRun = $data['dryRun'] ?? false;
            $force = $data['force'] ?? false;

            // Récupérer seulement les bibliothèques actives
            $libraries = $this->libraryRepository->findBy(['enabled' => true]);

            foreach ($libraries as $library) {
                $libraryId = $library->getId();
                if (null === $libraryId) {
                    continue; // Skip libraries without ID
                }

                $this->taskService->createTask(
                    Task::TYPE_SCAN_LIBRARY,
                    null,
                    $libraryId,
                    $library->getName(),
                    ['dry_run' => $dryRun, 'force_analysis' => $force],
                    3
                );
            }

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.all_libraries_scan_started'),
                'libraries_count' => \count($libraries),
            ]);
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/{id}/artists', name: 'library_artists', methods: ['GET'])]
    public function artists(Library $library, Request $request): JsonResponse
    {
        $libraryId = $library->getId();
        if (null === $libraryId) {
            return $this->json(['error' => 'Library has no ID'], 400);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(10, (int) $request->query->get('limit', 50)));

        try {
            // Get paginated artists for this library
            $artists = $this->musicLibraryManager->getAllArtistsPaginated($page, $limit, $libraryId);
            $totalCount = $this->musicLibraryManager->countAllArtists($libraryId);

            $totalPages = ceil($totalCount / $limit);
            $hasNext = $page < $totalPages;

            $data = [];
            foreach ($artists as $artist) {
                $artistStats = $this->statisticsService->getArtistStatistics($artist);
                $albumCount = $artistStats ? ($artistStats['totalAlbums'] + $artistStats['totalSingles']) : $artist->getAlbums()->count();

                $data[] = [
                    'id' => $artist->getId(),
                    'name' => $artist->getName(),
                    'mbid' => $artist->getMbid(),
                    'country' => $artist->getCountry(),
                    'type' => $artist->getType(),
                    'status' => $artist->getStatus(),
                    'monitored' => $artist->isMonitored(),
                    'albumCount' => $albumCount,
                    'imageUrl' => $artist->getImageUrl(),
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
            $this->logger->error('Error in library artists pagination: ' . $e->getMessage());

            return $this->json([
                'success' => false,
                'error' => 'Error loading artists3',
            ], 500);
        }
    }

    #[Route('/{id}/edit', name: 'library_edit', methods: ['PUT'])]
    public function edit(Library $library, Request $request): JsonResponse
    {
        try {
            /** @var array{name: string, path: string, enabled?: bool, scanAutomatically?: bool, scanInterval?: int} $data */
            $data = json_decode($request->getContent(), true);

            // Name and path are required fields
            if (empty($data['name']) || empty($data['path'])) {
                return $this->json([
                    'success' => false,
                    'error' => 'Name and path are required',
                ], 400);
            }

            $library->setName($data['name']);
            $library->setPath($data['path']);
            $library->setEnabled($data['enabled'] ?? $library->isEnabled());
            $library->setScanAutomatically($data['scanAutomatically'] ?? $library->isScanAutomatically());
            $library->setScanInterval($data['scanInterval'] ?? $library->getScanInterval());

            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => 'Library updated successfully',
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Error updating library: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'library_delete', methods: ['DELETE'])]
    public function delete(Library $library): JsonResponse
    {
        try {
            $libraryId = $library->getId();
            if (null === $libraryId) {
                return $this->json([
                    'success' => false,
                    'error' => 'Library has no ID',
                ], 400);
            }

            // Check if the library has any artists by querying the repository
            $artistCount = $this->musicLibraryManager->countAllArtists($libraryId);

            if ($artistCount > 0) {
                return $this->json([
                    'success' => false,
                    'error' => $this->translator->trans('library.cannot_delete_has_artists', ['%count%' => $artistCount]),
                ], 400);
            }

            $this->entityManager->remove($library);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('library.deleted_success'),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('library.error_deleting') . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}
