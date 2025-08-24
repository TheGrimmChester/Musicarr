<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\LibraryRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/scan')]
class ScanController extends AbstractController
{
    public function __construct(
        private LibraryRepository $libraryRepository
    ) {
    }

    #[Route('/libraries', name: 'app_scan_libraries', methods: ['GET'])]
    public function scanLibraries(): Response
    {
        $libraries = $this->libraryRepository->findBy(['enabled' => true]);

        return $this->render('unmatched_track/scan.html.twig', [
            'libraries' => $libraries,
        ]);
    }
}
