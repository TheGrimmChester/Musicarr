<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Configuration;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Configuration>
 */
class ConfigurationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Configuration::class);
    }

    public function save(Configuration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Configuration $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find configuration by key.
     */
    public function findByKey(string $key): ?Configuration
    {
        return $this->findOneBy(['key' => $key]);
    }

    /**
     * Get all configurations as key-value pairs.
     */
    public function getAllAsArray(): array
    {
        $configurations = $this->findAll();
        $result = [];

        foreach ($configurations as $config) {
            $result[$config->getKey()] = $config->getParsedValue();
        }

        return $result;
    }

    /**
     * Get configurations by key prefix.
     */
    public function findByKeyPrefix(string $prefix): array
    {
        /** @var Configuration[] $result */
        $result = $this->createQueryBuilder('c')
            ->andWhere('c.key LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.key', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Find configurations by key prefix.
     */
    public function findConfigurationsByPrefix(string $prefix): array
    {
        /** @var Configuration[] $result */
        $result = $this->createQueryBuilder('c')
            ->where('c.key LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->orderBy('c.key', 'ASC')
            ->getQuery()
            ->getResult();

        return $result;
    }

    /**
     * Clear all configurations (useful for testing).
     */
    public function clearAll(): void
    {
        $configs = $this->findAll();

        foreach ($configs as $config) {
            $this->remove($config);
        }

        $this->getEntityManager()->flush();
    }
}
