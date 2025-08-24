<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Plugin;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Plugin>
 *
 * @method Plugin|null find($id, $lockMode = null, $lockVersion = null)
 * @method Plugin|null findOneBy(array $criteria, array $orderBy = null)
 * @method Plugin[]    findAll()
 * @method Plugin[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class PluginRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Plugin::class);
    }

    public function findByName(string $name): ?Plugin
    {
        return $this->findOneBy(['name' => $name]);
    }
}
