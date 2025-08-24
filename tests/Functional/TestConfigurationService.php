<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Entity\Configuration;
use App\Repository\ConfigurationRepository;
use Doctrine\ORM\EntityManagerInterface;

class TestConfigurationService
{
    private EntityManagerInterface $entityManager;
    private ConfigurationRepository $configurationRepository;

    public function __construct(EntityManagerInterface $entityManager, ConfigurationRepository $configurationRepository)
    {
        $this->entityManager = $entityManager;
        $this->configurationRepository = $configurationRepository;
    }

    /**
     * Create a test configuration entry.
     */
    public function createConfiguration(string $key, $value): Configuration
    {
        $config = new Configuration();
        $config->setKey($key);
        $config->setParsedValue($value);

        $this->entityManager->persist($config);
        $this->entityManager->flush();

        return $config;
    }

    /**
     * Set a configuration value (create or update).
     */
    public function setConfiguration(string $key, $value): Configuration
    {
        $existingConfig = $this->configurationRepository->findOneBy(['key' => $key]);

        if ($existingConfig) {
            $existingConfig->setParsedValue($value);
            $this->entityManager->flush();

            return $existingConfig;
        }

        return $this->createConfiguration($key, $value);
    }

    /**
     * Create multiple test configurations at once.
     */
    public function createConfigurations(array $configs): void
    {
        foreach ($configs as $key => $value) {
            $this->createConfiguration($key, $value);
        }
    }

    /**
     * Create album import test configuration.
     */
    public function createAlbumImportTestConfig(): void
    {
        $this->createConfigurations([
            'album_import.primary_types' => '["Album","EP","Single"]',
            'album_import.secondary_types' => '["Studio"]',
            'album_import.release_statuses' => '["official"]',
        ]);
    }

    /**
     * Create association test configuration.
     */
    public function createAssociationTestConfig(): void
    {
        $this->createConfigurations([
            'association.auto_association' => '1',
            'association.auto_associate' => '1',
            'association.confidence_threshold' => '0.8',
            'association.max_results' => '10',
        ]);
    }

    /**
     * Create audio quality test configuration.
     */
    public function createAudioQualityTestConfig(): void
    {
        $this->createConfigurations([
            'audio_quality.enabled' => '1',
            'audio_quality.min_bitrate' => '192',
            'audio_quality.preferred_format' => 'mp3',
            'audio_quality.analyze_existing' => '1',
        ]);
    }

    /**
     * Create downloader test configuration.
     */
    public function createDownloaderTestConfig(): void
    {
        $this->createConfigurations([
            'downloader.enabled' => '1',
            'downloader.type' => 'slskd',
            'downloader.host' => 'localhost',
            'downloader.port' => '5030',
            'downloader.username' => 'testuser',
            'downloader.password' => 'testpass',
        ]);
    }

    /**
     * Create file naming test configuration.
     */
    public function createFileNamingTestConfig(): void
    {
        $this->createConfigurations([
            'file_naming.enabled' => '1',
            'file_naming.pattern' => '{artist}/{album}/{track_number} - {title}',
            'file_naming.replace_spaces' => '1',
            'file_naming.max_length' => '255',
            'file_naming.case_sensitive' => '0',
            'file_naming.preserve_original' => '1',
            'file_naming.backup_extension' => '.bak',
        ]);
    }

    /**
     * Create metadata test configuration.
     */
    public function createMetadataTestConfig(): void
    {
        $this->createConfigurations([
            'metadata.enabled' => '1',
            'metadata.provider' => 'musicbrainz',
            'metadata.auto_update' => '1',
            'metadata.include_artwork' => '1',
        ]);
    }

    /**
     * Create library test configuration.
     */
    public function createLibraryTestConfig(): void
    {
        $this->createConfigurations([
            'library.enabled' => '1',
            'library.root_directory' => '/test/library',
            'library.scan_interval' => '3600',
            'library.auto_scan' => '1',
        ]);
    }

    /**
     * Create system test configuration.
     */
    public function createSystemTestConfig(): void
    {
        $this->createConfigurations([
            'system.debug' => '1',
            'system.log_level' => 'debug',
            'system.timezone' => 'UTC',
            'system.locale' => 'en',
        ]);
    }

    /**
     * Clear all test configurations.
     */
    public function clearAllConfigurations(): void
    {
        $configs = $this->configurationRepository->findAll();
        foreach ($configs as $config) {
            $this->entityManager->remove($config);
        }
        $this->entityManager->flush();
    }

    /**
     * Get configuration value by key.
     */
    public function getConfiguration(string $key): ?string
    {
        $config = $this->configurationRepository->findOneBy(['key' => $key]);

        return $config ? $config->getValue() : null;
    }

    /**
     * Check if configuration exists.
     */
    public function hasConfiguration(string $key): bool
    {
        return null !== $this->configurationRepository->findOneBy(['key' => $key]);
    }

    /**
     * Update existing configuration.
     */
    public function updateConfiguration(string $key, $value): void
    {
        $config = $this->configurationRepository->findOneBy(['key' => $key]);
        if ($config) {
            $config->setValue((string) $value);
            $this->entityManager->flush();
        }
    }
}
