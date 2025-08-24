<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\Album;
use App\Entity\Artist;
use App\Manager\MediaImageManager;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/media')]
class MediaController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MediaImageManager $mediaImageManager
    ) {
    }

    #[Route('/artist/{id}', name: 'media_artist_image', methods: ['GET'])]
    public function serveArtistImage(int $id, Request $request): Response
    {
        $artist = $this->entityManager->getRepository(Artist::class)->find($id);
        if (!$artist) {
            return new Response('Not Found', 404);
        }

        // Compute expected path depending on config
        $path = $this->mediaImageManager->resolveArtistImagePath($artist);
        if (!$path || !is_file($path)) {
            return new Response('Not Found', 404);
        }

        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->setMaxAge(86400); // 24h
        $response->setPublic();
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->headers->set('Content-Type', $this->guessMimeType($path));

        // ETag/Last-Modified for cache validation
        $etag = md5_file($path) ?: null;
        $lastModified = new DateTimeImmutable('@' . filemtime($path));
        if ($etag) {
            $response->setEtag($etag);
        }
        $response->setLastModified($lastModified);

        if ($response->isNotModified($request)) {
            return $response; // returns 304
        }

        return $response;
    }

    #[Route('/album/{id}', name: 'media_album_cover', methods: ['GET'])]
    public function serveAlbumCover(int $id, Request $request): Response
    {
        $album = $this->entityManager->getRepository(Album::class)->find($id);
        if (!$album) {
            return new Response('Not Found', 404);
        }

        $path = $this->mediaImageManager->resolveAlbumCoverPath($album);
        if (!$path || !is_file($path)) {
            return new Response('Not Found', 404);
        }

        $response = new BinaryFileResponse($path);
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->setPublic();
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, basename($path));
        $response->headers->set('Content-Type', $this->guessMimeType($path));

        $etag = md5_file($path) ?: null;
        $lastModified = new DateTimeImmutable('@' . filemtime($path));
        if ($etag) {
            $response->setEtag($etag);
        }
        $response->setLastModified($lastModified);

        if ($response->isNotModified($request)) {
            return $response;
        }

        return $response;
    }

    private function guessMimeType(string $path): string
    {
        $ext = mb_strtolower(pathinfo($path, \PATHINFO_EXTENSION));

        return match ($ext) {
            'png' => 'image/png',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            default => 'image/jpeg',
        };
    }
}
