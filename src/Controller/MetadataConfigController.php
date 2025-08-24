<?php

declare(strict_types=1);

namespace App\Controller;

use App\Configuration\Config\ConfigurationFactory;
use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/metadata-config')]
class MetadataConfigController extends AbstractController
{
    public function __construct(
        private ConfigurationDomainRegistry $domainRegistry,
        private ConfigurationFactory $configurationFactory,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {
    }

    #[Route('/', name: 'metadata_config_index', methods: ['GET'])]
    public function index(): Response
    {
        // Get the metadata domain
        $metadataDomain = $this->domainRegistry->getDomain('metadata.');
        if ($metadataDomain) {
            // Only initialize defaults if there's no existing configuration
            $existingConfig = $metadataDomain->getAllConfig();
            if (empty($existingConfig)) {
                $metadataDomain->initializeDefaults();
            }
        }

        // Get configuration using the domain (which includes saved values from database)
        $domainConfig = $metadataDomain ? $metadataDomain->getAllConfig() : [];

        // Prefix keys for template compatibility (expects metadata.* keys)
        $config = [];
        foreach ($domainConfig as $key => $value) {
            $config['metadata.' . $key] = $value;
        }

        return $this->render('metadata/config.html.twig', [
            'config' => $config,
        ]);
    }

    #[Route('/save', name: 'metadata_config_save', methods: ['POST'])]
    public function save(Request $request): JsonResponse
    {
        /** @var array{base_dir?: string, save_in_library?: bool} $data */
        $data = json_decode($request->getContent(), true) ?? [];
        $baseDir = (string) ($data['base_dir'] ?? '/app/public/metadata');
        $saveInLibrary = (bool) ($data['save_in_library'] ?? false);

        // Normalize and create directory if missing
        if (!is_dir($baseDir)) {
            try {
                // Suppress warnings for mkdir to avoid PHP warnings in logs
                if (!@mkdir($baseDir, 0755, true)) {
                    return $this->json([
                        'success' => false,
                        'error' => 'Could not create base directory',
                    ], 400);
                }
            } catch (Exception $e) {
                return $this->json([
                    'success' => false,
                    'error' => 'Error creating base directory: ' . $e->getMessage(),
                ], 400);
            }
        }

        if (!is_dir($baseDir) || !is_writable($baseDir)) {
            return $this->json([
                'success' => false,
                'error' => 'Base directory is not writable or could not be created',
            ], 400);
        }

        try {
            // Validate the configuration data
            if (!$this->configurationFactory->validateConfiguration('metadata.', $data)) {
                return $this->json([
                    'success' => false,
                    'error' => 'Invalid configuration data',
                ], 400);
            }

            // Process the configuration data
            $processedConfig = $this->configurationFactory->createConfiguration('metadata.', $data);

            // Save the configuration to the database
            $this->saveConfigurationToDatabase($processedConfig);

            // Ensure default subfolders exist when not saving in library
            if (!$saveInLibrary) {
                try {
                    $artistsDir = mb_rtrim($baseDir, '/') . '/artists';
                    $coversDir = mb_rtrim($baseDir, '/') . '/covers';

                    if (!is_dir($artistsDir)) {
                        @mkdir($artistsDir, 0755, true);
                    }
                    if (!is_dir($coversDir)) {
                        @mkdir($coversDir, 0755, true);
                    }
                } catch (Exception $e) {
                    $this->logger->warning('Could not create default subdirectories', [
                        'error' => $e->getMessage(),
                        'base_dir' => $baseDir,
                    ]);
                }
            }

            $this->logger->info('Metadata configuration updated', [
                'base_dir' => $baseDir,
                'save_in_library' => $saveInLibrary,
            ]);

            return $this->json(['success' => true]);
        } catch (Exception $e) {
            return $this->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Save configuration to database.
     */
    private function saveConfigurationToDatabase(array $config): void
    {
        foreach ($config as $key => $value) {
            $fullKey = 'metadata.' . $key;

            // Find existing configuration or create new one
            $existingConfig = $this->entityManager->getRepository(Configuration::class)
                ->findOneBy(['key' => $fullKey]);

            if (null === $existingConfig) {
                $existingConfig = new Configuration();
                $existingConfig->setKey($fullKey);
            }

            $existingConfig->setParsedValue($value);
            $this->entityManager->persist($existingConfig);
        }

        $this->entityManager->flush();
    }
}
