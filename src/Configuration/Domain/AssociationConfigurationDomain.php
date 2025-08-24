<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\AssociationConfigurationTreeBuilder;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;

class AssociationConfigurationDomain extends AbstractConfigurationDomain
{
    public function __construct(
        AssociationConfigurationTreeBuilder $treeBuilder,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($treeBuilder);
        $this->setEntityManager($entityManager);
    }

    public function getDomainPrefix(): string
    {
        return 'association.';
    }

    public function getConfigurationKeys(): array
    {
        return [
            'auto_association',
            'min_score',
            'exact_artist_match',
            'exact_album_match',
            'exact_duration_match',
            'exact_year_match',
            'exact_title_match',
        ];
    }

    public function initializeDefaults(): void
    {
        $defaults = [
            'auto_association' => true,
            'min_score' => 100.0,
            'exact_artist_match' => true,
            'exact_album_match' => true,
            'exact_duration_match' => true,
            'exact_year_match' => true,
            'exact_title_match' => true,
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
            'auto_association' => true,
            'min_score' => 100.0,
            'exact_artist_match' => true,
            'exact_album_match' => true,
            'exact_duration_match' => true,
            'exact_year_match' => true,
            'exact_title_match' => true,
        ];
    }

    public function getAllConfig(): array
    {
        $configs = $this->getByPrefix($this->getDomainPrefix());

        $result = [];

        // Return configuration keys without domain prefix for template compatibility
        foreach ($this->getConfigurationKeys() as $key) {
            $result[$key] = $configs[$key] ?? null;
        }

        return $result;
    }

    // Domain-specific getter methods
    public function getMinimumScoreThreshold(): float
    {
        return (float) $this->get('min_score', 85.0);
    }

    public function isAutoAssociationEnabled(): bool
    {
        return $this->get('auto_association', true);
    }

    public function requiresExactArtistMatch(): bool
    {
        return $this->get('exact_artist_match', false);
    }

    public function requiresExactAlbumMatch(): bool
    {
        return $this->get('exact_album_match', false);
    }

    public function requiresExactDurationMatch(): bool
    {
        return $this->get('exact_duration_match', false);
    }

    public function requiresExactYearMatch(): bool
    {
        return $this->get('exact_year_match', false);
    }

    public function requiresExactTitleMatch(): bool
    {
        return $this->get('exact_title_match', false);
    }

    /**
     * Set configuration value.
     */
    public function setValue(string $key, mixed $value): void
    {
        $fullKey = $this->getDomainPrefix() . $key;
        $this->set($fullKey, $value);
    }

    /**
     * Get configuration value by key.
     */
    protected function get(string $key, mixed $default = null): mixed
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

    /**
     * Get total configuration count for this domain.
     */
    public function getTotalConfigurationCount(): int
    {
        $configurations = $this->entityManager->getRepository(Configuration::class)
            ->findByKeyPrefix($this->getDomainPrefix());

        return \count($configurations);
    }
}
