<?php

declare(strict_types=1);

namespace App\Manager;

use App\Entity\Album;
use App\Entity\Track;
use App\Repository\AlbumRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class AlbumStatusManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private AlbumRepository $albumRepository,
        private LoggerInterface $logger,
        private TranslatorInterface $translator
    ) {
    }

    /**
     * Met à jour le statut d'un album après l'analyse d'une piste.
     */
    public function updateAlbumStatusAfterTrackAnalysis(Track $track): void
    {
        $album = $track->getAlbum();
        if (!$album) {
            return;
        }

        $this->updateAlbumStatus($album);
    }

    /**
     * Met à jour le statut d'un album.
     */
    public function updateAlbumStatus(Album $album): void
    {
        $tracks = $album->getTracks();
        $totalTracks = $tracks->count();
        $tracksWithFiles = 0;
        $tracksDownloaded = 0;
        $tracksAnalyzed = 0;

        foreach ($tracks as $track) {
            if ($track->isHasFile()) {
                ++$tracksWithFiles;

                if ($track->isDownloaded()) {
                    ++$tracksDownloaded;
                }

                // Vérifier si au moins un fichier de la piste a été analysé
                foreach ($track->getFiles() as $file) {
                    if ($file->getQuality()) {
                        ++$tracksAnalyzed;

                        break; // Une seule analyse par piste suffit
                    }
                }
            }
        }

        // Mettre à jour les statuts de l'album
        $album->setHasFile($tracksWithFiles > 0);
        $album->setDownloaded($tracksDownloaded === $totalTracks && $totalTracks > 0);

        // Déterminer le statut de l'album
        $status = $this->determineAlbumStatus($totalTracks, $tracksWithFiles, $tracksDownloaded);
        $album->setStatus($status);

        // Ajouter un champ pour le statut d'analyse si nécessaire
        if (method_exists($album, 'setAnalyzed')) {
            $album->setAnalyzed($tracksAnalyzed === $totalTracks && $totalTracks > 0);
        }

        $this->albumRepository->save($album, true);

        $this->logger->info("Statut de l'album mis à jour", [
            'album_id' => $album->getId(),
            'album_title' => $album->getTitle(),
            'total_tracks' => $totalTracks,
            'tracks_with_files' => $tracksWithFiles,
            'tracks_downloaded' => $tracksDownloaded,
            'tracks_analyzed' => $tracksAnalyzed,
            'status' => $status,
        ]);
    }

    /**
     * Détermine le statut de l'album basé sur les pistes disponibles.
     */
    private function determineAlbumStatus(int $totalTracks, int $tracksWithFiles, int $tracksDownloaded): string
    {
        if (0 === $totalTracks) {
            return 'empty';
        }

        if (0 === $tracksWithFiles) {
            return 'missing';
        }

        if ($tracksDownloaded === $totalTracks) {
            return 'downloaded';
        }

        if ($tracksWithFiles > 0 && $tracksWithFiles < $totalTracks) {
            return 'partial';
        }

        return 'unknown';
    }

    /**
     * Met à jour tous les statuts d'albums.
     */
    public function updateAllAlbumStatuses(): int
    {
        $albums = $this->albumRepository->findAll();
        $updated = 0;

        foreach ($albums as $album) {
            $this->updateAlbumStatus($album);
            ++$updated;
        }

        $this->logger->info($this->translator->trans('api.log.album_statuses_updated', ['count' => $updated]));

        return $updated;
    }

    /**
     * Met à jour les statuts des albums d'un artiste.
     */
    public function updateArtistAlbumStatuses(int $artistId): int
    {
        $albums = $this->albumRepository->findBy(['artist' => $artistId]);
        $updated = 0;

        foreach ($albums as $album) {
            $this->updateAlbumStatus($album);
            ++$updated;
        }

        $this->logger->info($this->translator->trans('api.log.album_statuses_updated_artist', ['count' => $updated, 'artist_id' => $artistId]));

        return $updated;
    }

    /**
     * Récupère les statistiques des statuts d'albums.
     */
    public function getAlbumStatusStats(): array
    {
        $stats = [
            'total' => 0,
            'empty' => 0,
            'missing' => 0,
            'partial' => 0,
            'downloaded' => 0,
            'unknown' => 0,
        ];

        $albums = $this->albumRepository->findAll();

        foreach ($albums as $album) {
            ++$stats['total'];
            $status = $album->getStatus() ?? 'unknown';

            if (isset($stats[$status])) {
                ++$stats[$status];
            } else {
                ++$stats['unknown'];
            }
        }

        return $stats;
    }

    /**
     * Nettoie les albums vides.
     */
    public function cleanupEmptyAlbums(): int
    {
        $albums = $this->albumRepository->findBy(['status' => 'empty']);
        $removed = 0;

        foreach ($albums as $album) {
            $this->entityManager->remove($album);
            ++$removed;
        }

        $this->entityManager->flush();
        $this->logger->info($this->translator->trans('manager.album_status.empty_albums_removed', ['%count%' => $removed]));

        return $removed;
    }

    /**
     * Récupère les albums par statut.
     */
    public function getAlbumsByStatus(string $status, int $limit = 50): array
    {
        return $this->albumRepository->findBy(['status' => $status], ['title' => 'ASC'], $limit);
    }

    /**
     * Force la mise à jour du statut d'un album.
     */
    public function forceUpdateAlbumStatus(Album $album): void
    {
        $this->logger->info($this->translator->trans('manager.album_status.forced_status_update', ['%title%' => $album->getTitle()]));
        $this->updateAlbumStatus($album);
    }

    /**
     * Vérifie la cohérence des statuts d'albums.
     */
    public function validateAlbumStatuses(): array
    {
        $issues = [];
        $albums = $this->albumRepository->findAll();

        foreach ($albums as $album) {
            $tracks = $album->getTracks();
            $totalTracks = $tracks->count();
            $tracksWithFiles = 0;
            $tracksDownloaded = 0;

            foreach ($tracks as $track) {
                if ($track->isHasFile()) {
                    ++$tracksWithFiles;
                }
                if ($track->isDownloaded()) {
                    ++$tracksDownloaded;
                }
            }

            $expectedStatus = $this->determineAlbumStatus($totalTracks, $tracksWithFiles, $tracksDownloaded);
            $actualStatus = $album->getStatus();

            if ($expectedStatus !== $actualStatus) {
                $issues[] = [
                    'album_id' => $album->getId(),
                    'album_title' => $album->getTitle(),
                    'expected_status' => $expectedStatus,
                    'actual_status' => $actualStatus,
                    'total_tracks' => $totalTracks,
                    'tracks_with_files' => $tracksWithFiles,
                    'tracks_downloaded' => $tracksDownloaded,
                ];
            }
        }

        return $issues;
    }
}
