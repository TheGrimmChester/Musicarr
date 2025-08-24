<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Track;
use App\Repository\TrackRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/track')]
class TrackController extends AbstractController
{
    public function __construct(
        private TrackRepository $trackRepository,
        private TranslatorInterface $translator
    ) {
    }

    #[Route('/{id}', name: 'track_show', methods: ['GET'])]
    public function show(Track $track): Response
    {
        // Charger les relations si elles ne sont pas déjà chargées
        $trackId = $track->getId();
        if (null === $trackId) {
            throw $this->createNotFoundException($this->translator->trans('track.not_found'));
        }
        $track = $this->trackRepository->findWithRelations($trackId);

        if (!$track) {
            throw $this->createNotFoundException($this->translator->trans('track.not_found'));
        }

        return $this->render('track/show.html.twig', [
            'track' => $track,
        ]);
    }

    #[Route('/{id}/data', name: 'track_data', methods: ['GET'])]
    public function getTrackData(Track $track): JsonResponse
    {
        $data = [
            'id' => $track->getId(),
            'title' => $track->getTitle(),
            'duration' => $track->getDuration(),
            'hasFile' => $track->isHasFile(),
            'downloaded' => $track->isDownloaded(),
        ];

        // Include all files for the track
        $files = [];
        foreach ($track->getFiles() as $file) {
            $files[] = [
                'id' => $file->getId(),
                'filePath' => $file->getFilePath(),
                'fileSize' => $file->getFileSize(),
                'quality' => $file->getQuality(),
                'format' => $file->getFormat(),
                'duration' => $file->getDuration(),
            ];
        }

        $data['files'] = $files;

        return $this->json($data);
    }
}
