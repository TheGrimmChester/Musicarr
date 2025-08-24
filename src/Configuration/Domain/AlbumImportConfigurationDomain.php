<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\AlbumImportConfigurationTreeBuilder;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;

class AlbumImportConfigurationDomain extends AbstractConfigurationDomain
{
    public function __construct(
        AlbumImportConfigurationTreeBuilder $treeBuilder,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($treeBuilder);
        $this->setEntityManager($entityManager);
    }

    public function getDomainPrefix(): string
    {
        return 'album_import.';
    }

    public function getConfigurationKeys(): array
    {
        return [
            'primary_types',
            'secondary_types',
            'release_statuses',
        ];
    }

    public function initializeDefaults(): void
    {
        $defaults = [
            'primary_types' => ['Album', 'EP', 'Single'],
            'secondary_types' => ['Studio'],
            'release_statuses' => ['official'],
        ];

        foreach ($defaults as $key => $defaultValue) {
            $fullKey = $this->getDomainPrefix() . $key;

            // Only set defaults if the configuration doesn't exist yet
            $existingConfig = $this->entityManager->getRepository(Configuration::class)
                ->findOneBy(['key' => $fullKey]);

            if (null === $existingConfig) {
                $this->set($fullKey, $defaultValue);
            }
        }
    }

    /**
     * Get hardcoded default values for this domain.
     */
    protected function getHardcodedDefaults(): array
    {
        return [
            'primary_types' => ['Album', 'EP', 'Single'],
            'secondary_types' => ['Studio'],
            'release_statuses' => ['official'],
        ];
    }

    public function getAllConfig(): array
    {
        $configs = $this->getByPrefix($this->getDomainPrefix());

        $result = [];

        // Return configuration keys without domain prefix for template compatibility
        foreach ($this->getConfigurationKeys() as $key) {
            // $configs keys are already without the domain prefix
            $result[$key] = $configs[$key] ?? null;
        }

        return $result;
    }

    // Domain-specific getter methods
    public function getPrimaryTypes(): array
    {
        return $this->get('primary_types', ['Album', 'EP', 'Single']);
    }

    public function getSecondaryTypes(): array
    {
        return $this->get('secondary_types', ['Studio']);
    }

    public function getReleaseStatuses(): array
    {
        return $this->get('release_statuses', ['official']);
    }

    public function isReleaseStatusAllowed(string $status): bool
    {
        return \in_array($status, $this->getReleaseStatuses(), true);
    }

    /**
     * Get configuration value by key.
     */
    private function get(string $key, mixed $default = null): mixed
    {
        $fullKey = $this->getDomainPrefix() . $key;

        $config = $this->entityManager->getRepository(Configuration::class)
            ->findOneBy(['key' => $fullKey]);

        return $config?->getParsedValue() ?? $default;
    }

    /**
     * Set configuration value in the database.
     */
    protected function set(string $key, mixed $value, ?string $description = null): void
    {
        $config = $this->entityManager->getRepository(Configuration::class)
            ->findOneBy(['key' => $key]);

        if (null === $config) {
            $config = new Configuration();
            $config->setKey($key);
            if (null !== $description) {
                $config->setDescription($description);
            }
        }

        $config->setParsedValue($value);
        $this->entityManager->persist($config);
        $this->entityManager->flush();
    }

    /**
     * Get configurations by prefix.
     */
    private function getByPrefix(string $prefix): array
    {
        $configurations = $this->entityManager->getRepository(Configuration::class)
            ->findByKeyPrefix($prefix);

        $result = [];
        foreach ($configurations as $config) {
            // Strip the prefix from the key
            $key = str_replace($prefix, '', $config->getKey());
            $result[$key] = $config->getParsedValue();
        }

        return $result;
    }
}
