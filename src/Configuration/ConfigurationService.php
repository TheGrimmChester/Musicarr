<?php

declare(strict_types=1);

namespace App\Configuration;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

class ConfigurationService implements ConfigurationServiceInterface
{
    public function __construct(
        private ConfigurationRepository $configurationRepository,
        private EntityManagerInterface $entityManager
    ) {
    }

    /**
     * Get a configuration value.
     */
    public function get(string $key, mixed $defaultValue = null): mixed
    {
        $config = $this->configurationRepository->findByKey($key);

        if (null === $config) {
            return $defaultValue;
        }

        return $config->getParsedValue();
    }

    /**
     * Set a configuration value.
     */
    public function set(string $key, mixed $value, ?string $description = ''): void
    {
        $config = $this->configurationRepository->findByKey($key);

        if (null === $config) {
            $config = new Configuration();
            $config->setKey($key);
            $this->entityManager->persist($config);
        }

        $config->setParsedValue($value);
        $config->setDescription($description);

        $this->entityManager->flush();
    }

    /**
     * Delete a configuration.
     */
    public function delete(string $key): bool
    {
        $config = $this->configurationRepository->findByKey($key);

        if (null === $config) {
            return false;
        }

        $this->entityManager->remove($config);
        $this->entityManager->flush();

        return true;
    }

    /**
     * Get all configuration values for a specific prefix.
     */
    public function getConfigByPrefix(string $prefix): array
    {
        $configs = $this->configurationRepository->findByKeyPrefix($prefix);
        $result = [];

        foreach ($configs as $config) {
            $result[$config->getKey()] = $config->getParsedValue();
        }

        return $result;
    }

    /**
     * Get all configuration values for a specific prefix (alias for getConfigByPrefix).
     */
    public function getByPrefix(string $prefix): array
    {
        return $this->getConfigByPrefix($prefix);
    }

    /**
     * Get downloader configuration.
     */
    public function getDownloaderConfig(): array
    {
        return $this->getConfigByPrefix('downloader.');
    }

    /**
     * Get association configuration.
     */
    public function getAssociationConfig(): array
    {
        return $this->getConfigByPrefix('association.');
    }

    /**
     * Get metadata configuration.
     */
    public function getMetadataConfig(): array
    {
        return $this->getConfigByPrefix('metadata.');
    }

    /**
     * Get album import configuration.
     */
    public function getAlbumImportConfig(): array
    {
        return $this->getConfigByPrefix('album_import.');
    }

    /**
     * Check if a configuration key exists.
     */
    public function has(string $key): bool
    {
        return null !== $this->configurationRepository->findByKey($key);
    }

    /**
     * Get all configuration keys.
     */
    public function getAllKeys(): array
    {
        $configs = $this->configurationRepository->findAll();

        return array_map(fn (Configuration $config) => $config->getKey(), $configs);
    }

    /**
     * Get all configuration values.
     */
    public function getAll(): array
    {
        $configs = $this->configurationRepository->findAll();
        $result = [];

        foreach ($configs as $config) {
            $result[$config->getKey()] = $config->getParsedValue();
        }

        return $result;
    }

    /**
     * Clear all configuration (useful for testing).
     */
    public function clearAll(): void
    {
        $configs = $this->configurationRepository->findAll();

        foreach ($configs as $config) {
            $this->entityManager->remove($config);
        }

        $this->entityManager->flush();
    }
}
