<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Exception;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    /**
     * Sauvegarde une entité Album.
     */
    public function save(Album $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve un album par son Release Group MBID.
     */
    public function findByReleaseGroupMbid(string $releaseGroupMbid): ?Album
    {
        return $this->findOneBy(['releaseGroupMbid' => $releaseGroupMbid]);
    }

    /**
     * Vérifie si un Release Group MBID existe déjà.
     */
    public function existsByReleaseGroupMbid(string $releaseGroupMbid): bool
    {
        return $this->count(['releaseGroupMbid' => $releaseGroupMbid]) > 0;
    }

    /**
     * Crée un album avec vérification d'unicité du Release Group MBID.
     */
    public function createAlbumWithReleaseGroupCheck(string $releaseGroupMbid, callable $albumCreator): Album
    {
        // Vérifier si un album avec ce Release Group MBID existe déjà
        $existingAlbum = $this->findByReleaseGroupMbid($releaseGroupMbid);
        if ($existingAlbum) {
            throw new Exception("Un album avec le Release Group MBID {$releaseGroupMbid} existe déjà (ID: {$existingAlbum->getId()})");
        }

        // Créer le nouvel album
        $album = $albumCreator();
        $album->setReleaseGroupMbid($releaseGroupMbid);

        // Persister l'album
        $this->save($album, true);

        return $album;
    }

    /**
     * Trouve un album par titre et artiste avec correspondance flexible pour les apostrophes.
     */
    public function findByTitleAndArtistFlexible(string $title, Artist $artist): ?Album
    {
        // Normaliser les apostrophes pour la correspondance
        $normalizedTitle = $this->normalizeApostrophes($title);

        /** @var Album[] $results */
        $results = $this->createQueryBuilder('a')
            ->andWhere('a.artist = :artist')
            ->andWhere('(a.title = :title OR a.title = :normalizedTitle)')
            ->setParameter('artist', $artist)
            ->setParameter('title', $title)
            ->setParameter('normalizedTitle', $normalizedTitle)
            ->getQuery()
            ->getResult();

        // Si aucune correspondance exacte, essayer avec comparaison normalisée
        if (empty($results)) {
            /** @var Album[] $allAlbums */
            $allAlbums = $this->createQueryBuilder('a')
                ->andWhere('a.artist = :artist')
                ->setParameter('artist', $artist)
                ->getQuery()
                ->getResult();

            foreach ($allAlbums as $album) {
                $albumTitle = $album->getTitle();
                if (null === $albumTitle) {
                    continue;
                }
                $albumNormalizedTitle = $this->normalizeApostrophes($albumTitle);
                if (0 === strcasecmp($normalizedTitle, $albumNormalizedTitle)) {
                    return $album;
                }
            }
        }

        return !empty($results) ? $results[0] : null;
    }

    /**
     * Normalise les apostrophes pour la correspondance.
     */
    private function normalizeApostrophes(string $text): string
    {
        // Replace straight apostrophes with curly apostrophes
        return str_replace('’', "'", $text);
    }

    /**
     * Recherche des albums pour un artiste donné (pour la correspondance manuelle de pistes).
     */
    public function findByArtistForManualMatching(int $artistId, string $query = '', int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->join('a.artist', 'ar')
            ->addSelect('ar')
            ->where('ar.id = :artistId')
            ->setParameter('artistId', $artistId)
            ->orderBy('a.title', 'ASC');

        if (!empty($query)) {
            $queryBuilder->andWhere('a.title LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        /** @var Album[] $result */
        $result = $queryBuilder->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
