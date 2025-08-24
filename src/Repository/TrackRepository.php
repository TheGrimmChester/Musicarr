<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Track;
use App\Entity\TrackFile;
use DateTime;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Track>
 */
class TrackRepository extends ServiceEntityRepository
{
    use DoctrineEntityIteratingTrait;

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Track::class);
    }

    public function findAllIterable(): iterable
    {
        return $this->iterateOverEntities($this->getEntityManager(), Track::class);
    }

    /**
     * Trouve une piste par artiste, album et titre en utilisant les relations.
     */
    public function findByArtistAlbumAndTitle(string $artistName, string $albumTitle, string $title): ?Track
    {
        // Nettoyer le nom de l'album (enlever "EP", "LP", etc.)
        $cleanAlbumTitle = $this->cleanAlbumTitle($albumTitle);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(a.title = :albumTitle OR a.title = :cleanAlbumTitle)')
            ->andWhere("REPLACE(t.title, '’', '''') LIKE :title")
            ->setParameter('artistName', $artistName)
            ->setParameter('albumTitle', $albumTitle)
            ->setParameter('cleanAlbumTitle', $cleanAlbumTitle)
            ->setParameter('title', $title)
            ->getQuery()
            ->getResult();

        // Return the first result if multiple found, or null if none found
        /** @var Track|null $result */
        $result = !empty($results) ? $results[0] : null;

        return $result;
    }

    /**
     * Trouve une piste par album et titre.
     */
    public function findByAlbumAndTitle(int $albumId, string $title): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->andWhere('a.id = :albumId')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->setParameter('albumId', $albumId)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->getQuery()
            ->getResult();

        // Return the first result if multiple found, or null if none found
        /** @var Track|null $result */
        $result = !empty($results) ? $results[0] : null;

        return $result;
    }

    /**
     * Nettoie le titre de l'album en enlevant les suffixes courants.
     */
    private function cleanAlbumTitle(string $albumTitle): string
    {
        $cleanTitle = $albumTitle;

        return mb_trim($cleanTitle);
    }

    /**
     * Trouve une piste par artiste et titre avec correspondance flexible.
     */
    public function findByArtistAndTitleFlexible(string $artistName, string $title): ?Track
    {
        // Nettoyer le titre pour la correspondance
        $cleanTitle = $this->cleanTrackTitle($title);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere("REPLACE(t.title, '’', '''') LIKE :title OR REPLACE(t.title, '’', '''') LIKE :cleanTitle")
            ->setParameter('artistName', $artistName)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->getQuery()
            ->getResult();

        // If no exact match found, try with normalized comparison
        if (empty($results)) {
            $allTracks = $this->createQueryBuilder('t')
                ->join('t.album', 'a')
                ->join('a.artist', 'ar')
                ->andWhere('ar.name = :artistName')
                ->setParameter('artistName', $artistName)
                ->getQuery()
                ->getResult();

            foreach ($allTracks as $track) {
                $trackCleanTitle = $this->cleanTrackTitle($track->getTitle());
                if (0 === strcasecmp($cleanTitle, $trackCleanTitle)) {
                    return $track;
                }
            }
        }

        // Return the first result if multiple found, or null if none found
        /** @var Track|null $result */
        $result = !empty($results) ? $results[0] : null;

        return $result;
    }

    /**
     * Nettoie le titre de la piste pour la correspondance.
     */
    private function cleanTrackTitle(string $title): string
    {
        // Remove track number prefixes (e.g., "03. ", "1. ", "01. ", etc.)
        $cleanTitle = preg_replace('/^\d+\.\s*/', '', $title);
        if (null === $cleanTitle) {
            $cleanTitle = $title; // Fallback to original title
        }

        // Supprimer les suffixes courants
        $suffixes = [
            ' ft. ',
            ' featuring ',
            ' feat. ',
            ' (Acoustic Version)',
            ' (Acoustic)',
            ' (Remix)',
            ' (Radio Edit)',
            ' (Explicit)',
            ' (Clean)',
            ' (Album Version)',
            ' (Single Version)',
            ' (Extended Version)',
            ' (Short Version)',
            ' (Instrumental)',
            ' (Karaoke Version)',
            ' (Live)',
            ' (Studio Version)',
            ' (Original Mix)',
            ' (Club Mix)',
            ' (Radio Mix)',
            ' (Album Mix)',
            ' (Single Mix)',
            ' (Extended Mix)',
            ' (Short Mix)',
            ' (Instrumental Mix)',
            ' (Karaoke Mix)',
            ' (Live Mix)',
            ' (Studio Mix)',
            ' (Original)',
            ' (Club)',
            ' (Radio)',
            ' (Album)',
            ' (Single)',
            ' (Extended)',
            ' (Short)',
            ' (Instrumental)',
            ' (Karaoke)',
            ' (Live)',
            ' (Studio)',
        ];

        foreach ($suffixes as $suffix) {
            $cleanTitle = str_replace($suffix, '', $cleanTitle);
        }

        // Remove any remaining artist names after "ft." or "feat."
        $cleanTitle = preg_replace('/\s+(?:ft\.|feat\.|featuring)\s+[^)]+$/', '', $cleanTitle);
        if (null === $cleanTitle) {
            $cleanTitle = $title; // Fallback to original title
        }

        // Normalize dashes and spaces for better matching
        $cleanTitle = str_replace(['‐', '–', '—', '-'], ' ', $cleanTitle); // Replace various dashes with space

        // Normalize apostrophes using the StringSimilarity service
        $cleanTitle = str_replace('’', "'", $cleanTitle); // Replace straight apostrophes with curly apostrophes

        $cleanTitle = preg_replace('/\s+/', ' ', $cleanTitle); // Normalize multiple spaces to single space
        if (null === $cleanTitle) {
            $cleanTitle = $title; // Fallback to original title
        }

        return mb_trim($cleanTitle);
    }

    /**
     * Trouve les pistes manquantes (hasFile = false).
     */
    public function findMissingTracks(): array
    {
        return $this->createQueryBuilder('t')
            ->andWhere('t.hasFile = false')
            ->orderBy('t.artistName', 'ASC')
            ->addOrderBy('t.albumTitle', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une piste par chemin de fichier.
     */
    public function findByFilePath(string $filePath): ?Track
    {
        $results = $this->createQueryBuilder('t')
            ->join('t.files', 'f')
            ->andWhere('f.filePath = :filePath')
            ->setParameter('filePath', $filePath)
            ->getQuery()
            ->getResult();

        // Return the first result if multiple found, or null if none found
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Trouve des pistes par nom de fichier.
     */
    public function findByFileName(string $fileName): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.files', 'f')
            ->andWhere('f.filePath LIKE :fileName')
            ->setParameter('fileName', '%' . $fileName)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve des pistes par IDs avec leurs relations Album et Artist chargées.
     */
    public function findByIdsWithRelations(array $ids): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.album', 'a')
            ->leftJoin('a.artist', 'ar')
            ->addSelect('a', 'ar')
            ->where('t.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche des pistes pour un album donné (pour la correspondance manuelle de pistes).
     */
    public function findByAlbumForManualMatching(int $albumId, string $query = '', int $limit = 50): array
    {
        $qb = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->addSelect('a', 'ar')
            ->where('a.id = :albumId')
            ->setParameter('albumId', $albumId)
            ->orderBy('t.trackNumber', 'ASC')
            ->addOrderBy('t.title', 'ASC');

        if (!empty($query)) {
            $qb->andWhere('t.title LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        return $qb->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une piste par ID avec ses relations Album et Artist chargées.
     */
    public function findWithRelations(int $id): ?Track
    {
        return $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('t.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Trouve toutes les pistes avec fichiers et leurs relations Album et Artist chargées.
     */
    public function findAllWithFilesAndRelations(): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('t.hasFile = true')
            ->orderBy('ar.name', 'ASC')
            ->addOrderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve des pistes filtrées avec pagination pour le renommage.
     */
    public function findFilteredTracksForRenaming(
        int $page = 1,
        int $limit = 50,
        string $search = '',
        string $artistFilter = '',
        string $albumFilter = '',
        string $titleFilter = '',
        ?string $_excludePattern = null
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('t.hasFile = true');

        // Apply search filter
        if (!empty($search)) {
            $qb->andWhere('(ar.name LIKE :search OR a.title LIKE :search OR t.title LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply artist filter
        if (!empty($artistFilter)) {
            $qb->andWhere('ar.name LIKE :artistFilter')
                ->setParameter('artistFilter', '%' . $artistFilter . '%');
        }

        // Apply album filter
        if (!empty($albumFilter)) {
            $qb->andWhere('a.title LIKE :albumFilter')
                ->setParameter('albumFilter', '%' . $albumFilter . '%');
        }

        // Apply title filter
        if (!empty($titleFilter)) {
            $qb->andWhere('t.title LIKE :titleFilter')
                ->setParameter('titleFilter', '%' . $titleFilter . '%');
        }

        // Get total count for pagination
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(t.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination and ordering
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('ar.name', 'ASC')
            ->addOrderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC');

        $tracks = $qb->getQuery()->getResult();

        return [
            'tracks' => $tracks,
            'total' => $total,
        ];
    }

    /**
     * Trouve des pistes filtrées avec pagination pour le renommage en utilisant le champ needRename.
     */
    public function findFilteredTracksForRenamingWithNeedRename(
        int $page = 1,
        int $limit = 50,
        string $search = '',
        string $artistFilter = '',
        string $albumFilter = '',
        string $titleFilter = ''
    ): array {
        $qb = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->join('t.files', 'tf')
            ->andWhere('t.hasFile = true')
            ->andWhere('tf.needRename = true');

        // Apply search filter
        if (!empty($search)) {
            $qb->andWhere('(ar.name LIKE :search OR a.title LIKE :search OR t.title LIKE :search)')
                ->setParameter('search', '%' . $search . '%');
        }

        // Apply artist filter
        if (!empty($artistFilter)) {
            $qb->andWhere('ar.name LIKE :artistFilter')
                ->setParameter('artistFilter', '%' . $artistFilter . '%');
        }

        // Apply album filter
        if (!empty($albumFilter)) {
            $qb->andWhere('a.title LIKE :albumFilter')
                ->setParameter('albumFilter', '%' . $albumFilter . '%');
        }

        // Apply title filter
        if (!empty($titleFilter)) {
            $qb->andWhere('t.title LIKE :titleFilter')
                ->setParameter('titleFilter', '%' . $titleFilter . '%');
        }

        // Get total count for pagination
        $countQb = clone $qb;
        $total = $countQb->select('COUNT(DISTINCT t.id)')->getQuery()->getSingleScalarResult();

        // Apply pagination and ordering
        $qb->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->orderBy('ar.name', 'ASC')
            ->addOrderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC');

        $tracks = $qb->getQuery()->getResult();

        return [
            'tracks' => $tracks,
            'total' => $total,
        ];
    }

    /**
     * Met à jour la qualité d'une piste basée sur l'analyse du fichier.
     */
    public function updateTrackQuality(Track $track, string $qualityString): void
    {
        // Update quality for all files of the track
        foreach ($track->getFiles() as $file) {
            $file->setQuality($qualityString);
            $this->getEntityManager()->persist($file);
        }
        $this->getEntityManager()->flush();
    }

    /**
     * Met à jour la qualité d'une piste basée sur l'analyse du fichier.
     */
    public function updateTrackFileQuality(TrackFile $trackFile, string $qualityString): void
    {
        $trackFile->setQuality($qualityString);
        $this->getEntityManager()->persist($trackFile);
        $this->getEntityManager()->flush();
    }

    /**
     * Sauvegarde une piste.
     */
    public function save(Track $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve toutes les pistes d'un artiste.
     */
    public function findByArtist(int $artistId): array
    {
        return $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.id = :artistId')
            ->setParameter('artistId', $artistId)
            ->orderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve des pistes similaires par artiste et titre.
     */
    public function findSimilarTracksByArtist(int $artistId, string $title): array
    {
        $cleanTitle = $this->cleanTrackTitle($title);

        return $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.id = :artistId')
            ->andWhere('(t.title LIKE :title OR t.title LIKE :cleanTitle)')
            ->setParameter('artistId', $artistId)
            ->setParameter('title', '%' . $title . '%')
            ->setParameter('cleanTitle', '%' . $cleanTitle . '%')
            ->orderBy('a.title', 'ASC')
            ->addOrderBy('t.trackNumber', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve une piste par artiste, titre et année.
     */
    public function findByArtistTitleAndYear(string $artistName, string $title, int $year): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->andWhere('a.releaseDate >= :startDate')
            ->andWhere('a.releaseDate < :endDate')
            ->setParameter('artistName', $artistName)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->setParameter('startDate', new DateTime($year . '-01-01'))
            ->setParameter('endDate', new DateTime(($year + 1) . '-01-01'))
            ->getQuery()
            ->getResult();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Trouve une piste par artiste, titre et numéro de piste.
     */
    public function findByArtistTitleAndTrackNumber(string $artistName, string $title, string $trackNumber): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->andWhere('t.trackNumber = :trackNumber')
            ->setParameter('artistName', $artistName)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->setParameter('trackNumber', $trackNumber)
            ->getQuery()
            ->getResult();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Trouve une piste par artiste, titre, année et numéro de piste.
     */
    public function findByArtistTitleYearAndTrackNumber(string $artistName, string $title, int $year, string $trackNumber): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->andWhere('a.releaseDate >= :startDate')
            ->andWhere('a.releaseDate < :endDate')
            ->andWhere('t.trackNumber = :trackNumber')
            ->setParameter('artistName', $artistName)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->setParameter('startDate', new DateTime($year . '-01-01'))
            ->setParameter('endDate', new DateTime(($year + 1) . '-01-01'))
            ->setParameter('trackNumber', $trackNumber)
            ->getQuery()
            ->getResult();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Trouve une piste par artiste, album, titre et année.
     */
    public function findByArtistAlbumTitleAndYear(string $artistName, string $albumTitle, string $title, int $year): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);
        $cleanAlbumTitle = $this->cleanAlbumTitle($albumTitle);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(a.title = :albumTitle OR a.title = :cleanAlbumTitle)')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->andWhere('a.releaseDate >= :startDate')
            ->andWhere('a.releaseDate < :endDate')
            ->setParameter('artistName', $artistName)
            ->setParameter('albumTitle', $albumTitle)
            ->setParameter('cleanAlbumTitle', $cleanAlbumTitle)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->setParameter('startDate', new DateTime($year . '-01-01'))
            ->setParameter('endDate', new DateTime(($year + 1) . '-01-01'))
            ->getQuery()
            ->getResult();

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Trouve une piste par artiste, album, titre, année et numéro de piste.
     */
    public function findByArtistAlbumTitleYearAndTrackNumber(string $artistName, string $albumTitle, string $title, int $year, string $trackNumber): ?Track
    {
        $cleanTitle = $this->cleanTrackTitle($title);
        $cleanAlbumTitle = $this->cleanAlbumTitle($albumTitle);

        $results = $this->createQueryBuilder('t')
            ->join('t.album', 'a')
            ->join('a.artist', 'ar')
            ->andWhere('ar.name = :artistName')
            ->andWhere('(a.title = :albumTitle OR a.title = :cleanAlbumTitle)')
            ->andWhere('(t.title = :title OR t.title = :cleanTitle)')
            ->andWhere('a.releaseDate >= :startDate')
            ->andWhere('a.releaseDate < :endDate')
            ->andWhere('t.trackNumber = :trackNumber')
            ->setParameter('artistName', $artistName)
            ->setParameter('albumTitle', $albumTitle)
            ->setParameter('cleanAlbumTitle', $cleanAlbumTitle)
            ->setParameter('title', $title)
            ->setParameter('cleanTitle', $cleanTitle)
            ->setParameter('startDate', new DateTime($year . '-01-01'))
            ->setParameter('endDate', new DateTime(($year + 1) . '-01-01'))
            ->setParameter('trackNumber', $trackNumber)
            ->getQuery()
            ->getResult();

        return !empty($results) ? $results[0] : null;
    }
}
