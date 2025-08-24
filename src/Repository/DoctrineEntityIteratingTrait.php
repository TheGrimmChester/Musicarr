<?php

declare(strict_types=1);

namespace App\Repository;

use Doctrine\ORM\EntityManagerInterface;

trait DoctrineEntityIteratingTrait
{
    /**
     * @template T of object
     *
     * @param class-string<T> $className  Entity class to fetch
     * @param positive-int    $batchCount Batch cound to load at once
     *
     * @return iterable<array-key, T>
     */
    private function iterateOverEntities(
        EntityManagerInterface $doctrine,
        string $className,
        int $batchCount = 100
    ): iterable {
        $r = $doctrine->getRepository($className);

        // current offset to navigate over the entire set
        $offset = 0;

        do {
            /** @var T[] $entities */
            $entities = $r->findBy([], null, $batchCount, $offset);

            foreach ($entities as $entity) {
                yield $entity;
            }

            // increase the offset
            $offset += $batchCount;

            // important thing to remember
            // release objects from the ORM's internal memory
            $doctrine->clear();
        } while (\count($entities) > 0);
    }
}
