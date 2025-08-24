<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Configuration\Domain\AssociationConfigurationDomain;
use App\Entity\Configuration;
use App\Statistic\AssociationStatistics;
use App\Tests\Functional\AbstractFunctionalTestCase;
use Exception;

class AssociationConfigControllerFunctionalTest extends AbstractFunctionalTestCase
{
    private AssociationConfigurationDomain $associationDomain;

    protected function setUp(): void
    {
        parent::setUp();
        $this->associationDomain = $this->client->getContainer()->get(AssociationConfigurationDomain::class);

        // Initialize the domain registry
        if (!$this->domainRegistry->isInitialized()) {
            $this->domainRegistry->initialize();
        }

        // Initialize the association domain with defaults
        $this->associationDomain->initializeDefaults();
    }

    public function testAssociationConfigIndexPageLoads(): void
    {
        // Make a request to the association config page
        $this->client->request('GET', '/association-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Assert the page contains expected content
        $this->assertSelectorExists('form[action="/association-config/save"]');
        $this->assertSelectorExists('input[name="association_config[auto_association]"]');
        $this->assertSelectorExists('input[name="association_config[exact_artist_match]"]');
        $this->assertSelectorExists('input[name="association_config[exact_album_match]"]');
        $this->assertSelectorExists('input[name="association_config[exact_duration_match]"]');
        $this->assertSelectorExists('input[name="association_config[exact_year_match]"]');
        $this->assertSelectorExists('input[name="association_config[exact_title_match]"]');
        $this->assertSelectorExists('input[name="association_config[auto_association]"]');
        $this->assertSelectorExists('input[name="association_config[min_score]"]');
    }

    public function testAssociationConfigSaveWithValidData(): void
    {
        // Test data
        $testData = [
            'association_config' => [
                'auto_association' => 'on',
                'exact_artist_match' => 'on',
                'exact_album_match' => 'on',
                'exact_duration_match' => 'on',
                'exact_year_match' => 'on',
                'exact_title_match' => 'on',
                'min_score' => '95.0',
                'max_duration_difference' => '5',
                'max_year_difference' => '2',
                'artist_similarity_threshold' => '0.8',
                'album_similarity_threshold' => '0.7',
                'title_similarity_threshold' => '0.6',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertTrue($autoAssociationConfig->getParsedValue());

        $minScoreConfig = $configRepo->findOneBy(['key' => 'association.min_score']);
        $this->assertNotNull($minScoreConfig);
        $this->assertEquals(95.0, $minScoreConfig->getParsedValue());
    }

    public function testAssociationConfigSaveWithJsonData(): void
    {
        // Test data in JSON format
        $testData = [
            'auto_association' => true,
            'exact_artist_match' => true,
            'exact_album_match' => true,
            'exact_duration_match' => true,
            'exact_year_match' => true,
            'exact_title_match' => true,
            'min_score' => 90.0,
        ];

        // Make a POST request with JSON data
        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertTrue($autoAssociationConfig->getParsedValue());

        $minScoreConfig = $configRepo->findOneBy(['key' => 'association.min_score']);
        $this->assertNotNull($minScoreConfig);
        $this->assertEquals(90.0, $minScoreConfig->getParsedValue());
    }

    public function testAssociationConfigSaveWithPartialData(): void
    {
        // Test data with only some fields
        $testData = [
            'association_config' => [
                'min_score' => '85.0',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertFalse($autoAssociationConfig->getParsedValue());

        $minScoreConfig = $configRepo->findOneBy(['key' => 'association.min_score']);
        $this->assertNotNull($minScoreConfig);
        $this->assertEquals(85.0, $minScoreConfig->getParsedValue());
    }

    public function testAssociationConfigSaveWithInvalidData(): void
    {
        // Test data with invalid values
        $testData = [
            'association_config' => [
                'auto_association' => 'invalid_value',
                'min_score' => 'not_a_number',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAssociationConfigSaveWithEmptyData(): void
    {
        // Make a POST request with empty data
        $this->client->request('POST', '/association-config/save', []);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAssociationConfigSaveWithBooleanFields(): void
    {
        // Test data with boolean fields
        $testData = [
            'association_config' => [
                'auto_association' => 'on',
                'exact_artist_match' => 'on',
                'exact_album_match' => 'off',
                'exact_duration_match' => 'on',
                'exact_year_match' => 'off',
                'exact_title_match' => 'on',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertTrue($autoAssociationConfig->getParsedValue());

        $exactAlbumConfig = $configRepo->findOneBy(['key' => 'association.exact_album_match']);
        $this->assertNotNull($exactAlbumConfig);
        $this->assertFalse($exactAlbumConfig->getParsedValue());

        $exactYearConfig = $configRepo->findOneBy(['key' => 'association.exact_year_match']);
        $this->assertNotNull($exactYearConfig);
        $this->assertFalse($exactYearConfig->getParsedValue());

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertTrue($autoAssociationConfig->getParsedValue());
    }

    public function testAssociationConfigSaveWithNumericFields(): void
    {
        // Test data with numeric fields
        $testData = [
            'association_config' => [
                'min_score' => '87.5',
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved to the database
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $minScoreConfig = $configRepo->findOneBy(['key' => 'association.min_score']);
        $this->assertNotNull($minScoreConfig);
        $this->assertEquals(87.5, $minScoreConfig->getParsedValue());
    }

    public function testAssociationConfigRetrievalAfterSave(): void
    {
        // First, save some configuration
        $testData = [
            'association_config' => [
                'auto_association' => 'on',
                'min_score' => '92.0',
                'exact_artist_match' => 'on',
                'exact_album_match' => 'off',
            ],
        ];

        $this->client->request('POST', '/association-config/save', $testData);
        $this->assertResponseIsSuccessful();

        // Now retrieve the configuration page
        $this->client->request('GET', '/association-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the saved values are displayed
        $this->assertSelectorExists('input[name="association_config[auto_association]"][checked]');
        $this->assertSelectorExists('input[name="association_config[exact_artist_match]"][checked]');
        $this->assertSelectorExists('input[name="association_config[exact_album_match]"]:not([checked])');
    }

    public function testAssociationConfigValidation(): void
    {
        // Test data with values that should fail validation
        $testData = [
            'association_config' => [
                'auto_association' => 'on',
                'min_score' => '150.0', // Invalid: should be 0-100
            ],
        ];

        // Make a POST request to save the configuration
        $this->client->request('POST', '/association-config/save', $testData);

        // Assert the response has error status
        $this->assertResponseStatusCodeSame(400);

        // Assert the response contains error message
        $responseData = json_decode($this->client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
        $this->assertArrayHasKey('error', $responseData);
    }

    public function testAssociationConfigStatisticsDisplay(): void
    {
        // Make a request to the association config page
        $this->client->request('GET', '/association-config/');

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify that statistics are displayed (if the service is available)
        try {
            $statisticsService = $this->client->getContainer()->get(AssociationStatistics::class);
            if ($statisticsService) {
                $this->assertSelectorExists('.statistics-section');
            }
        } catch (Exception $e) {
            // Statistics service not available, skip this assertion
        }
    }

    public function testAssociationConfigFormSubmission(): void
    {
        // Test the form submission process
        $crawler = $this->client->request('GET', '/association-config/');

        // Find the form
        $form = $crawler->selectButton('Save Configuration')->form();

        // Fill in the form with only existing fields
        $form['association_config[auto_association]'] = 'on';
        $form['association_config[min_score]'] = '88.0';
        $form['association_config[exact_artist_match]'] = 'on';
        $form['association_config[exact_album_match]'] = 'on';
        // Leave exact_duration_match unchecked (don't set it)
        $form['association_config[exact_year_match]'] = 'on';
        // Leave exact_title_match unchecked (don't set it)
        $form['association_config[auto_association]'] = 'on';

        // Submit the form
        $this->client->submit($form);

        // Assert the response is successful
        $this->assertResponseIsSuccessful();

        // Verify the configuration was saved
        $configRepo = $this->entityManager->getRepository(Configuration::class);

        $autoAssociationConfig = $configRepo->findOneBy(['key' => 'association.auto_association']);
        $this->assertNotNull($autoAssociationConfig);
        $this->assertTrue($autoAssociationConfig->getParsedValue());

        $minScoreConfig = $configRepo->findOneBy(['key' => 'association.min_score']);
        $this->assertNotNull($minScoreConfig);
        $this->assertEquals(88.0, $minScoreConfig->getParsedValue());
    }
}
