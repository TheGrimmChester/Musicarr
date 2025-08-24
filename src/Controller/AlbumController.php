<?php

declare(strict_types=1);

namespace App\Controller;

use App\Client\MusicBrainzApiClient;
use App\Entity\Album;
use App\Entity\Task;
use App\Manager\MusicLibraryManager;
use App\Repository\AlbumRepository;
use App\Serializer\TrackDataSerializer;
use App\Task\TaskFactory;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use InvalidArgumentException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/album')]
class AlbumController extends AbstractController
{
    public function __construct(
        private MusicLibraryManager $musicLibraryManager,
        private MusicBrainzApiClient $musicBrainzApiClient,
        private EntityManagerInterface $entityManager,
        private TrackDataSerializer $trackDataSerializer,
        private TranslatorInterface $translator,
        private AlbumRepository $albumRepository,
        private TaskFactory $taskFactory
    ) {
    }

    #[Route('/', name: 'album_index', methods: ['GET'])]
    public function index(): Response
    {
        return $this->render('album/index.html.twig');
    }

    #[Route('/search', name: 'album_search', methods: ['GET'])]
    public function search(Request $request): Response
    {
        $query = $request->query->get('q', '');

        return $this->render('album/search.html.twig', [
            'query' => $query,
            'results' => [],
        ]);
    }

    #[Route('/{id}', name: 'album_show', methods: ['GET'])]
    public function show(Album $album): Response
    {
        $albumId = $album->getId();
        if (null === $albumId) {
            throw new InvalidArgumentException('Album ID is null');
        }

        $tracks = $this->musicLibraryManager->getAlbumTracks($albumId);

        // Group tracks by medium
        $tracksByMedium = [];
        foreach ($tracks as $track) {
            $medium = $track->getMedium();
            $mediumId = $medium ? $medium->getId() : 'null';

            if (!isset($tracksByMedium[$mediumId])) {
                $tracksByMedium[$mediumId] = [
                    'medium' => $medium,
                    'tracks' => [],
                ];
            }

            $tracksByMedium[$mediumId]['tracks'][] = $track;
        }

        // Sort by medium position
        uasort($tracksByMedium, function ($medium1, $medium2) {
            $medium1Pos = $medium1['medium'] ? $medium1['medium']->getPosition() : 0;
            $medium2Pos = $medium2['medium'] ? $medium2['medium']->getPosition() : 0;

            return $medium1Pos <=> $medium2Pos;
        });

        // Préparer les données des pistes pour le JavaScript
        $tracksData = $this->trackDataSerializer->serializeTracksData($tracks);

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'tracks' => $tracks,
            'tracksByMedium' => $tracksByMedium,
            'tracksData' => $tracksData,
        ]);
    }

    #[Route('/{id}/tracks', name: 'album_tracks', methods: ['GET'])]
    public function tracks(Album $album): JsonResponse
    {
        $albumId = $album->getId();
        if (null === $albumId) {
            return $this->json(['error' => 'Album ID is null'], 400);
        }
        $tracks = $this->musicLibraryManager->getAlbumTracks($albumId);

        $data = [];
        foreach ($tracks as $track) {
            $medium = $track->getMedium();

            // Get file information from all files
            $files = [];
            $totalDuration = 0;
            $totalFileSize = 0;

            foreach ($track->getFiles() as $file) {
                $files[] = [
                    'id' => $file->getId(),
                    'filePath' => $file->getFilePath(),
                    'fileSize' => $file->getFileSize(),
                    'quality' => $file->getQuality(),
                    'format' => $file->getFormat(),
                    'duration' => $file->getDuration(),
                ];
                $totalDuration += $file->getDuration();
                $totalFileSize += $file->getFileSize();
            }

            $data[] = [
                'id' => $track->getId(),
                'title' => $track->getTitle(),
                'mbid' => $track->getMbid(),
                'trackNumber' => $track->getTrackNumber(),
                'mediumNumber' => $track->getMediumNumber(),
                'medium' => $medium ? [
                    'id' => $medium->getId(),
                    'title' => $medium->getTitle(),
                    'position' => $medium->getPosition(),
                    'format' => $medium->getFormat(),
                    'discId' => $medium->getDiscId(),
                    'mbid' => $medium->getMbid(),
                    'displayName' => $medium->getDisplayName(),
                ] : null,
                'duration' => $totalDuration,
                'monitored' => $track->isMonitored(),
                'downloaded' => $track->isDownloaded(),
                'hasFile' => $track->isHasFile(),
                'files' => $files,
                'fileCount' => \count($files),
                'totalFileSize' => $totalFileSize,
            ];
        }

        // Group tracks by medium
        $mediaData = [];
        foreach ($tracks as $track) {
            $medium = $track->getMedium();
            $mediumId = $medium ? $medium->getId() : 'null';

            if (!isset($mediaData[$mediumId])) {
                $mediaData[$mediumId] = [
                    'medium' => $medium ? [
                        'id' => $medium->getId(),
                        'title' => $medium->getTitle(),
                        'position' => $medium->getPosition(),
                        'format' => $medium->getFormat(),
                        'discId' => $medium->getDiscId(),
                        'mbid' => $medium->getMbid(),
                        'displayName' => $medium->getDisplayName(),
                        'trackCount' => $medium->getTrackCount(),
                    ] : null,
                    'tracks' => [],
                ];
            }

            $trackData = null;
            foreach ($data as $trackDataItem) {
                if ($trackDataItem['id'] === $track->getId()) {
                    $trackData = $trackDataItem;

                    break;
                }
            }

            if ($trackData) {
                $mediaData[$mediumId]['tracks'][] = $trackData;
            }
        }

        return $this->json([
            'tracks' => $data,
            'mediums' => array_values($mediaData),
        ]);
    }

    #[Route('/{id}/toggle-monitor', name: 'album_toggle_monitor', methods: ['POST'])]
    public function toggleMonitor(Album $album): JsonResponse
    {
        $album->setMonitored(!$album->isMonitored());

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'monitored' => $album->isMonitored(),
        ]);
    }

    #[Route('/{id}/mark-downloaded', name: 'album_mark_downloaded', methods: ['POST'])]
    public function markDownloaded(Album $album): JsonResponse
    {
        $album->setDownloaded(true);
        $album->setHasFile(true);

        // Marque aussi toutes les pistes comme téléchargées
        foreach ($album->getTracks() as $track) {
            $track->setDownloaded(true);
            $track->setHasFile(true);
        }

        $this->entityManager->flush();

        return $this->json(['success' => true]);
    }

    #[Route('/{id}/mark-missing', name: 'album_mark_missing', methods: ['POST'])]
    public function markMissing(Album $album): JsonResponse
    {
        $album->setDownloaded(false);
        $album->setHasFile(false);

        $this->entityManager->flush();

        return $this->json([
            'success' => true,
            'downloaded' => false,
        ]);
    }

    #[Route('/{id}/other-releases', name: 'album_other_releases', methods: ['GET'])]
    public function getOtherReleases(Album $album): JsonResponse
    {
        if (!$album->getReleaseGroupMbid()) {
            return $this->json([
                'success' => false,
                'error' => 'Cet album n\'a pas de MBID MusicBrainz',
            ], 400);
        }

        try {
            $releases = $this->musicBrainzApiClient->getOtherReleases($album->getReleaseMbid());

            if (empty($releases)) {
                return $this->json([
                    'success' => true,
                    'releases' => [],
                    'message' => $this->translator->trans('album.no_other_releases_found'),
                ]);
            }

            return $this->json([
                'success' => true,
                'releases' => $releases,
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('album.cannot_fetch_other_releases'),
                'debug_message' => $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/change-release', name: 'album_change_release', methods: ['POST'])]
    public function changeRelease(Request $request, Album $album): JsonResponse
    {
        $newMbid = $request->request->get('mbid');

        if (!$newMbid || !\is_string($newMbid)) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('api.error.mbid_required_string'),
            ], 400);
        }

        try {
            // Récupérer les détails de la nouvelle release depuis MusicBrainz
            $releaseData = $this->musicBrainzApiClient->getAlbum($newMbid);
            if (!$releaseData) {
                return $this->json([
                    'success' => false,
                    'error' => $this->translator->trans('api.error.release_not_found'),
                ], 404);
            }

            // Mettre à jour l'album avec les nouvelles informations
            $album->setReleaseMbid($newMbid);
            $album->setTitle($releaseData['title'] ?? $album->getTitle());
            $album->setReleaseDate(new DateTime($releaseData['date'] ?? 'now'));

            // Réinitialiser les pistes car elles vont changer
            $albumId = $album->getId();
            if (null === $albumId) {
                return $this->json(['error' => 'Album ID is null'], 400);
            }

            $this->entityManager->flush();

            // Envoyer le message de resynchronisation
            $albumId = $album->getId();
            if (null === $albumId) {
                return $this->json(['error' => 'Album ID is null'], 400);
            }

            $task = $this->taskFactory->createTask(
                Task::TYPE_SYNC_ALBUM,
                null,
                $albumId,
                null,
                [
                    'new_mbid' => $newMbid,
                    'max_retries' => 3,
                ],
                3
            );

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('api.success.release_changed_successfully'),
                'album' => [
                    'id' => $album->getId(),
                    'title' => $album->getTitle(),
                    'mbid' => $album->getReleaseGroupMbid(),
                ],
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'Erreur lors du changement de release: ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/update', name: 'album_update', methods: ['POST'])]
    public function update(Album $album): JsonResponse
    {
        try {
            // Vérifier que l'album a un MBID
            if (!$album->getReleaseMbid()) {
                return $this->json([
                    'success' => false,
                    'error' => $this->translator->trans('album.no_mbid_cannot_update'),
                ], 400);
            }

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('album.updated_success', ['%title%' => $album->getTitle()]),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('api.log.update_error') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    #[Route('/{id}/delete', name: 'album_delete', methods: ['POST'])]
    public function delete(Album $album): JsonResponse
    {
        try {
            $albumTitle = $album->getTitle();
            $artist = $album->getArtist();
            if (null === $artist) {
                return $this->json([
                    'success' => false,
                    'error' => $this->translator->trans('album.no_artist_associated'),
                ], 400);
            }
            $artistName = $artist->getName();

            // Supprimer l'album (les pistes seront supprimées automatiquement via cascade)
            $this->entityManager->remove($album);
            $this->entityManager->flush();

            return $this->json([
                'success' => true,
                'message' => $this->translator->trans('album.deleted_success_with_artist', [
                    '%title%' => $albumTitle,
                    '%artist%' => $artistName,
                ]),
            ]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $this->translator->trans('api.error.album_delete_error') . ': ' . $e->getMessage(),
            ], 500);
        }
    }
}
