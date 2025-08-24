<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\AudioQualityConfigurationTreeBuilder;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;

class AudioQualityConfigurationDomain extends AbstractConfigurationDomain
{
    public function __construct(
        AudioQualityConfigurationTreeBuilder $treeBuilder,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($treeBuilder);
        $this->setEntityManager($entityManager);
    }

    public function getDomainPrefix(): string
    {
        return 'audio_quality.';
    }

    public function getConfigurationKeys(): array
    {
        return [
            'enabled',
            'min_bitrate',
            'preferred_format',
            'analyze_existing',
            'quality_threshold',
            'auto_convert',
            'convert_to_format',
        ];
    }

    public function initializeDefaults(): void
    {
        $defaults = [
            'enabled' => true,
            'min_bitrate' => 192,
            'preferred_format' => 'mp3',
            'analyze_existing' => false,
            'quality_threshold' => 0.8,
            'auto_convert' => false,
            'convert_to_format' => 'mp3',
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
            'enabled' => true,
            'min_bitrate' => 192,
            'preferred_format' => 'mp3',
            'analyze_existing' => false,
            'quality_threshold' => 0.8,
            'auto_convert' => false,
            'convert_to_format' => 'mp3',
        ];
    }

    public function getAllConfig(): array
    {
        $configs = $this->getByPrefix($this->getDomainPrefix());

        $result = [];

        // Return configuration keys without domain prefix for template compatibility
        foreach ($this->getConfigurationKeys() as $key) {
            $result[$key] = $configs[$this->getDomainPrefix() . $key] ?? null;
        }

        return $result;
    }

    // Domain-specific getter methods
    public function isEnabled(): bool
    {
        return $this->get('enabled', true);
    }

    public function getMinBitrate(): int
    {
        return (int) $this->get('min_bitrate', 192);
    }

    public function getPreferredFormat(): string
    {
        return (string) $this->get('preferred_format', 'mp3');
    }

    public function shouldAnalyzeExisting(): bool
    {
        return $this->get('analyze_existing', false);
    }

    public function getQualityThreshold(): float
    {
        return (float) $this->get('quality_threshold', 0.8);
    }

    public function isAutoConvertEnabled(): bool
    {
        return $this->get('auto_convert', false);
    }

    public function getConvertToFormat(): string
    {
        return (string) $this->get('convert_to_format', 'mp3');
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
