<?php

declare(strict_types=1);

namespace App\Configuration\Domain;

use App\Configuration\Config\MetadataConfigurationTreeBuilder;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;

class MetadataConfigurationDomain extends AbstractConfigurationDomain
{
    public function __construct(
        MetadataConfigurationTreeBuilder $treeBuilder,
        EntityManagerInterface $entityManager
    ) {
        parent::__construct($treeBuilder);
        $this->setEntityManager($entityManager);
    }

    public function getDomainPrefix(): string
    {
        return 'metadata.';
    }

    public function getConfigurationKeys(): array
    {
        return [
            'base_dir',
            'save_in_library',
            'image_path',
            'library_image_path',
        ];
    }

    public function initializeDefaults(): void
    {
        $defaults = $this->getDefaultValues();
        $descriptions = $this->getConfigurationDescriptions();

        foreach ($defaults as $key => $config) {
            $fullKey = $this->getDomainPrefix() . $key;
            $description = $descriptions[$key]['description'] ?? null;

            // Only set if not already exists
            if (null === $this->get($fullKey)) {
                $this->set($fullKey, $config, $description);
            }
        }
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
    public function getBaseDir(): string
    {
        return $this->get('base_dir', '/app/public/metadata');
    }

    public function shouldSaveInLibrary(): bool
    {
        return $this->get('save_in_library', false);
    }

    public function getImagePath(): string
    {
        return $this->get('image_path', 'images');
    }

    public function getLibraryImagePath(): string
    {
        return $this->get('library_image_path', 'library');
    }

    /**
     * Get hardcoded default values for this domain.
     */
    protected function getHardcodedDefaults(): array
    {
        return [
            'base_dir' => '/app/public/metadata',
            'save_in_library' => false,
            'image_path' => 'images',
            'library_image_path' => 'library',
        ];
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
            // Strip the prefix from the key for template/domain compatibility
            $key = str_replace($prefix, '', $config->getKey());
            $result[$key] = $config->getParsedValue();
        }

        return $result;
    }
}
