<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\UnmatchedTrack;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnmatchedTrack>
 *
 * @method UnmatchedTrack|null find($id, $lockMode = null, $lockVersion = null)
 * @method UnmatchedTrack|null findOneBy(array $criteria, array $orderBy = null)
 * @method UnmatchedTrack[]    findAll()
 * @method UnmatchedTrack[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UnmatchedTrackRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnmatchedTrack::class);
    }

    public function save(UnmatchedTrack $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(UnmatchedTrack $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Trouve les pistes non associées par bibliothèque.
     */
    public function findUnmatchedByLibrary(int $libraryId): array
    {
        return $this->createQueryBuilder('ut')
            ->andWhere('ut.library = :libraryId')
            ->andWhere('ut.isMatched = false')
            ->setParameter('libraryId', $libraryId)
            ->orderBy('ut.discoveredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les pistes par artiste, titre et album.
     */
    public function findByArtistAndTitle(?string $artist, ?string $title, ?string $album = null): array
    {
        $qb = $this->createQueryBuilder('ut')
            ->andWhere('ut.isMatched = false');

        if ($artist) {
            $qb->andWhere('ut.artist LIKE :artist')
                ->setParameter('artist', '%' . $artist . '%');
        }

        if ($title) {
            $qb->andWhere('ut.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }

        if ($album) {
            $qb->andWhere('ut.album LIKE :album')
                ->setParameter('album', '%' . $album . '%');
        }

        return $qb->orderBy('ut.discoveredAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Trouve les pistes par chemin de fichier.
     */
    public function findByFilePath(string $filePath): ?UnmatchedTrack
    {
        return $this->findOneBy(['filePath' => $filePath]);
    }

    /**
     * Compte les pistes non associées par bibliothèque.
     */
    public function countUnmatchedByLibrary(int $libraryId): int
    {
        $result = $this->createQueryBuilder('ut')
            ->select('COUNT(ut.id)')
            ->andWhere('ut.library = :libraryId')
            ->andWhere('ut.isMatched = false')
            ->setParameter('libraryId', $libraryId)
            ->getQuery()
            ->getSingleScalarResult();

        return (int) $result;
    }

    /**
     * Trouve les pistes non associées avec pagination.
     */
    public function findUnmatchedPaginated(int $page = 1, int $limit = 50, ?int $libraryId = null, ?string $artist = null, ?string $title = null, ?string $album = null): array
    {
        $offset = ($page - 1) * $limit;

        $qb = $this->createQueryBuilder('ut')
            ->andWhere('ut.isMatched = false');

        if ($libraryId) {
            $qb->andWhere('ut.library = :libraryId')
                ->setParameter('libraryId', $libraryId);
        }

        if ($artist) {
            $qb->andWhere('ut.artist LIKE :artist')
                ->setParameter('artist', '%' . $artist . '%');
        }

        if ($title) {
            $qb->andWhere('ut.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }

        if ($album) {
            $qb->andWhere('ut.album LIKE :album')
                ->setParameter('album', '%' . $album . '%');
        }

        return $qb->orderBy('ut.discoveredAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Compte le total des pistes non associées avec filtres.
     */
    public function countUnmatchedTotal(?int $libraryId = null, ?string $artist = null, ?string $title = null, ?string $album = null): int
    {
        $qb = $this->createQueryBuilder('ut')
            ->select('COUNT(ut.id)')
            ->andWhere('ut.isMatched = false');

        if ($libraryId) {
            $qb->andWhere('ut.library = :libraryId')
                ->setParameter('libraryId', $libraryId);
        }

        if ($artist) {
            $qb->andWhere('ut.artist LIKE :artist')
                ->setParameter('artist', '%' . $artist . '%');
        }

        if ($title) {
            $qb->andWhere('ut.title LIKE :title')
                ->setParameter('title', '%' . $title . '%');
        }

        if ($album) {
            $qb->andWhere('ut.album LIKE :album')
                ->setParameter('album', '%' . $album . '%');
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return (int) $result;
    }
}
