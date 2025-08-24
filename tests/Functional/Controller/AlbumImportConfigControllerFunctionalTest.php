<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Configuration\Domain\AlbumImportConfigurationDomain;
use App\Configuration\Domain\ConfigurationDomainRegistry;
use App\Entity\Configuration;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AlbumImportConfigControllerFunctionalTest extends WebTestCase
{
    private $client;
    private EntityManagerInterface $entityManager;
    private ConfigurationDomainRegistry $domainRegistry;
    private AlbumImportConfigurationDomain $albumImportDomain;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = $this->client->getContainer()->get(EntityManagerInterface::class);
        $this->domainRegistry = $this->client->getContainer()->get(ConfigurationDomainRegistry::class);
        $this->albumImportDomain = $this->client->getContainer()->get(AlbumImportConfigurationDomain::class);

        // Initialize the domain registry
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        // Initialize the album import domain with defaults
        $this->albumImportDomain->initializeDefaults();
    }

    protected function tearDown(): void
    {
        // Clean up test data
        $configRepo = $this->entityManager->getRepository(Configuration::class);
        $testConfigs = $configRepo->findBy(['key' => 'album_import.%']);
        foreach ($testConfigs as $config) {
            $this->entityManager->remove($config);
        }
        $this->entityManager->flush();

        parent::tearDown();
    }

    public function testAlbumImportConfigIndexPageLoads(): void
    {
        // Make a request to the album import config page
        $this->client->request('GET', '/album-import-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert the page contains expected content for MusicBrainz release types
        $this->assertSelectorExists('button[data-action="click->album-import-config#saveConfig"]');
        $this->assertSelectorExists('button[data-action="click->album-import-config#resetConfig"]');

        // Check for primary types
        $this->assertSelectorExists('input[id="primaryAlbum"]');
        $this->assertSelectorExists('input[id="primaryEP"]');
        $this->assertSelectorExists('input[id="primarySingle"]');
        $this->assertSelectorExists('input[id="primaryBroadcast"]');
        $this->assertSelectorExists('input[id="primaryOther"]');

        // Check for secondary types
        $this->assertSelectorExists('input[id="secondaryStudio"]');
        $this->assertSelectorExists('input[id="secondaryRemix"]');
        $this->assertSelectorExists('input[id="secondaryLive"]');
        $this->assertSelectorExists('input[id="secondaryCompilation"]');

        // Check for release statuses
        $this->assertSelectorExists('input[id="statusOfficial"]');
        $this->assertSelectorExists('input[id="statusPromotion"]');
        $this->assertSelectorExists('input[id="statusBootleg"]');
        $this->assertSelectorExists('input[id="statusPseudoRelease"]');
    }

    public function testAlbumImportConfigSaveWithValidData(): void
    {
        // Test data for MusicBrainz release types and statuses
        $testData = [
            'primary_types' => ['Album', 'EP', 'Single'],
            'secondary_types' => ['Studio', 'Remix', 'Live'],
            'release_statuses' => ['official', 'promotion'],
        ];

        // Make a POST request to save the configuration with JSON data
        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        // Clear entity manager cache to ensure fresh data
        $this->entityManager->clear();

        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album', 'EP', 'Single'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio', 'Remix', 'Live'], $secondaryTypesConfig->getParsedValue());

        $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
        $this->assertNotNull($releaseStatusesConfig);
        $this->assertEquals(['official', 'promotion'], $releaseStatusesConfig->getParsedValue());
    }

    public function testAlbumImportConfigSaveWithJsonData(): void
    {
        // Test data in JSON format
        $testData = [
            'primary_types' => ['Album', 'Single'],
            'secondary_types' => ['Studio', 'Live'],
            'release_statuses' => ['official', 'promotion', 'bootleg'],
        ];

        // Make a POST request with JSON data
        $this->client->request(
            'POST',
            '/album-import-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album', 'Single'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio', 'Live'], $secondaryTypesConfig->getParsedValue());

        $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
        $this->assertNotNull($releaseStatusesConfig);
        $this->assertEquals(['official', 'promotion', 'bootleg'], $releaseStatusesConfig->getParsedValue());
    }

    public function testAlbumImportConfigSaveWithPartialData(): void
    {
        // Test data with only some fields
        $testData = [
            'album_import_config' => [
                'primary_types' => ['Album'],
                'secondary_types' => ['Studio'],
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio'], $secondaryTypesConfig->getParsedValue());
    }

    public function testAlbumImportConfigSaveWithInvalidData(): void
    {
        // Test data with invalid values
        $testData = [
            'album_import_config' => [
                'primary_types' => 'not_an_array',
                'secondary_types' => 'not_an_array',
                'release_statuses' => 'not_an_array',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAlbumImportConfigSaveWithEmptyData(): void
    {
        // Make a POST request with empty data
        $this->client->request('POST', '/album-import-config/save', []);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAlbumImportConfigSaveWithArrayFields(): void
    {
        // Test data with array fields
        $testData = [
            'album_import_config' => [
                'primary_types' => ['Album', 'EP'],
                'secondary_types' => ['Studio', 'Live'],
                'release_statuses' => ['official'],
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        // Clear entity manager cache to ensure fresh data
        $this->entityManager->clear();

        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album', 'EP'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio', 'Live'], $secondaryTypesConfig->getParsedValue());

        $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
        $this->assertNotNull($releaseStatusesConfig);
        $this->assertEquals(['official'], $releaseStatusesConfig->getParsedValue());
    }

    public function testAlbumImportConfigSaveWithMultipleArrayValues(): void
    {
        // Test data with multiple array values
        $testData = [
            'album_import_config' => [
                'primary_types' => ['Album', 'EP', 'Single', 'Broadcast'],
                'secondary_types' => ['Studio', 'Live', 'Compilation', 'Remix'],
                'release_statuses' => ['official', 'promotion', 'bootleg', 'pseudo-release'],
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album', 'EP', 'Single', 'Broadcast'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio', 'Live', 'Compilation', 'Remix'], $secondaryTypesConfig->getParsedValue());

        $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
        $this->assertNotNull($releaseStatusesConfig);
        $this->assertEquals(['official', 'promotion', 'bootleg', 'pseudo-release'], $releaseStatusesConfig->getParsedValue());
    }

    public function testAlbumImportConfigSaveWithDifferentTypeOptions(): void
    {
        // Test data with different type combinations
        $typeOptions = [
            ['Album'],
            ['EP'],
            ['Single'],
            ['Album', 'EP'],
            ['Album', 'Single'],
            ['EP', 'Single'],
            ['Album', 'EP', 'Single'],
        ];

        foreach ($typeOptions as $types) {
            $testData = [
                'album_import_config' => [
                    'primary_types' => $types,
                    'secondary_types' => ['Studio'],
                    'release_statuses' => ['official'],
                ],
            ];

            // Make a POST request to save the configuration
            $this->client->request('POST', '/album-import-config/save', $testData);

            // Assert the response is successful
            $this->assertResponseIsSuccessful();

            // Verify the configuration was saved to the database
            // Clear entity manager cache to ensure fresh data
            $this->entityManager->clear();

            $configRepo = $this->entityManager->getRepository(Configuration::class);
            $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);

            $this->assertNotNull($primaryTypesConfig);
            $this->assertEquals($types, $primaryTypesConfig->getParsedValue());
        }
    }

    public function testAlbumImportConfigSaveWithDifferentStatusOptions(): void
    {
        // Test data with different status combinations
        $statusOptions = [
            ['official'],
            ['promotion'],
            ['bootleg'],
            ['pseudo-release'],
            ['official', 'promotion'],
            ['official', 'bootleg'],
            ['promotion', 'bootleg'],
            ['official', 'promotion', 'bootleg'],
        ];

        foreach ($statusOptions as $statuses) {
            $testData = [
                'album_import_config' => [
                    'primary_types' => ['Album'],
                    'secondary_types' => ['Studio'],
                    'release_statuses' => $statuses,
                ],
            ];

            // Make a POST request to save the configuration
            $this->client->request('POST', '/album-import-config/save', $testData);

            // Assert the response is successful
            $this->assertResponseIsSuccessful();

            // Verify the configuration was saved to the database
            // Clear entity manager cache to ensure fresh data
            $this->entityManager->clear();

            $configRepo = $this->entityManager->getRepository(Configuration::class);
            $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
            $this->assertNotNull($releaseStatusesConfig);
            $this->assertEquals($statuses, $releaseStatusesConfig->getParsedValue());
        }
    }

    public function testAlbumImportConfigRetrievalAfterSave(): void
    {
        // First, save some configuration
        $testData = [
            'album_import_config' => [
                'primary_types' => ['Album', 'EP'],
                'secondary_types' => ['Studio', 'Live'],
                'release_statuses' => ['official', 'promotion'],
            ],
        ];

        $this->client->request('POST', '/album-import-config/save', $testData);
        $this->assertResponseIsSuccessful();

        // Now retrieve the configuration page
        $this->client->request('GET', '/album-import-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the saved values are displayed
        $this->assertSelectorExists('input[id="primaryAlbum"][checked]');
        $this->assertSelectorExists('input[id="primaryEP"][checked]');
        $this->assertSelectorExists('input[id="secondaryStudio"][checked]');
        $this->assertSelectorExists('input[id="secondaryLive"][checked]');
        $this->assertSelectorExists('input[id="statusOfficial"][checked]');
        $this->assertSelectorExists('input[id="statusPromotion"][checked]');
    }

    public function testAlbumImportConfigValidation(): void
    {
        // Test data with values that should fail validation
        $testData = [
            'album_import_config' => [
                'primary_types' => 'not_an_array', // Invalid: should be array
                'secondary_types' => 'not_an_array', // Invalid: should be array
                'release_statuses' => 'not_an_array', // Invalid: should be array
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAlbumImportConfigFormSubmission(): void
    {
        // Test the form submission process by sending data directly to the endpoint
        $testData = [
            'album_import_config' => [
                'primary_types' => ['Album', 'EP', 'Single'],
                'secondary_types' => ['Studio', 'Live'],
                'release_statuses' => ['official', 'promotion'],
            ],
        ];

        // Submit the form data
        $this->client->request('POST', '/album-import-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved
        // Clear entity manager cache to ensure fresh data
        $this->entityManager->clear();

        $configRepo = $this->entityManager->getRepository(Configuration::class);
        $primaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.primary_types']);
        $this->assertNotNull($primaryTypesConfig);
        $this->assertEquals(['Album', 'EP', 'Single'], $primaryTypesConfig->getParsedValue());

        $secondaryTypesConfig = $configRepo->findOneBy(['key' => 'album_import.secondary_types']);
        $this->assertNotNull($secondaryTypesConfig);
        $this->assertEquals(['Studio', 'Live'], $secondaryTypesConfig->getParsedValue());

        $releaseStatusesConfig = $configRepo->findOneBy(['key' => 'album_import.release_statuses']);
        $this->assertNotNull($releaseStatusesConfig);
        $this->assertEquals(['official', 'promotion'], $releaseStatusesConfig->getParsedValue());
    }

    public function testAlbumImportConfigDefaultValues(): void
    {
        // Make a request to the album import config page
        $this->client->request('GET', '/album-import-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify that default values are displayed
        $this->assertSelectorExists('input[id="primaryAlbum"][checked]');
        $this->assertSelectorExists('input[id="primaryEP"][checked]');
        $this->assertSelectorExists('input[id="primarySingle"][checked]');
        $this->assertSelectorExists('input[id="secondaryStudio"][checked]');
        $this->assertSelectorExists('input[id="statusOfficial"][checked]');
    }
}
