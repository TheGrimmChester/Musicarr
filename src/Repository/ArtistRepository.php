<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Artist;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Artist>
 */
class ArtistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Artist::class);
    }

    /**
     * Recherche des artistes par nom.
     */
    public function searchByName(string $query, int $limit = 10): array
    {
        /** @var Artist[] $result */
        $result = $this->createQueryBuilder('a')
            ->where('a.name LIKE :query')
            ->setParameter('query', '%' . $query . '%')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Récupère tous les artistes.
     */
    public function findAllWithLibrary(): array
    {
        /** @var Artist[] $result */
        $result = $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Trouve un artiste par nom exact.
     */
    public function findByName(string $name): ?Artist
    {
        return $this->findOneBy(['name' => $name]);
    }

    /**
     * Trouve des artistes par nom (recherche partielle).
     */
    public function findByNamePartial(string $name, int $limit = 10): array
    {
        /** @var Artist[] $result */
        $result = $this->createQueryBuilder('a')
            ->where('a.name LIKE :name')
            ->setParameter('name', '%' . $name . '%')
            ->orderBy('a.name', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Recherche des artistes pour la correspondance manuelle de pistes.
     */
    public function searchForManualMatching(string $query = '', int $limit = 20): array
    {
        $queryBuilder = $this->createQueryBuilder('a')
            ->orderBy('a.name', 'ASC');

        if (!empty($query)) {
            $queryBuilder->where('a.name LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        /** @var Artist[] $result */
        $result = $queryBuilder->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return $result;
    }
}
