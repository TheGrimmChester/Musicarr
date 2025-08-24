<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LibraryRepository;
use App\Statistic\StatisticsService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    public function __construct(
        private StatisticsService $statisticsService,
        private LibraryRepository $libraryRepository
    ) {
    }

    #[Route('/', name: 'home')]
    public function index(): Response
    {
        // Récupère les statistiques globales
        $stats = $this->getGlobalStats();

        // Get enabled libraries count
        $enabledLibrariesCount = $this->getEnabledLibrariesCount();

        return $this->render('home/index.html.twig', [
            'stats' => $stats,
            'enabledLibrariesCount' => $enabledLibrariesCount,
        ]);
    }

    private function getGlobalStats(): array
    {
        // Get comprehensive statistics summary from the statistics service
        $summary = $this->statisticsService->getStatisticsSummary();

        return [
            'totalArtists' => $summary['artists'],
            'totalAlbums' => $summary['albums'],
            'totalSingles' => $summary['singles'],
            'totalTracks' => $summary['tracks'],
            'totalLibraries' => $summary['libraries'],
            'downloadedAlbums' => $summary['downloaded_albums'],
            'downloadedSingles' => $summary['downloaded_singles'],
            'downloadedTracks' => $summary['downloaded_tracks'],
            'downloadProgress' => $summary['album_completion_rate'],
            'singleProgress' => $summary['single_completion_rate'],
            'trackProgress' => $summary['track_completion_rate'],
            // Legacy fields for backward compatibility
            'monitoredArtists' => 0, // This would need to be calculated separately if needed
            'monitoredAlbums' => 0,  // This would need to be calculated separately if needed
        ];
    }

    private function getEnabledLibrariesCount(): int
    {
        return $this->libraryRepository->count(['enabled' => true]);
    }
}
