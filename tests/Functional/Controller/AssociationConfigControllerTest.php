<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Configuration\Domain\AbstractConfigurationDomain;
use App\Entity\Configuration;
use App\Tests\Functional\AbstractFunctionalTestCase;

class AssociationConfigControllerTest extends AbstractFunctionalTestCase
{
    public function testAssociationConfigIndexPageLoads(): void
    {
        $this->client->request('GET', '/association-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Association Configuration');
    }

    public function testAssociationConfigIndexWithExistingConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        $this->client->request('GET', '/association-config/');

        $this->assertResponseIsSuccessful();
        $this->assertPageContainsText('Association Configuration');
    }

    public function testAssociationConfigSaveNewConfiguration(): void
    {
        $testData = [
            'auto_association' => true,
            'min_score' => 75.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('association.auto_association', true);
        $this->assertConfigurationExists('association.min_score', 75.0);
    }

    public function testAssociationConfigUpdateExistingConfiguration(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        $updateData = [
            'auto_association' => false,
            'min_score' => 90.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was updated
        $this->assertConfigurationExists('association.auto_association', false);
        $this->assertConfigurationExists('association.min_score', 90.0);
    }

    public function testAssociationConfigPartialUpdate(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        $partialData = [
            'min_score' => 70.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($partialData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify only specified field was updated
        $this->assertConfigurationExists('association.min_score', 70.0);
        // Other fields should remain unchanged
        $this->assertConfigurationExists('association.auto_association', true);
    }

    public function testAssociationConfigSaveEmptyData(): void
    {
        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();
    }

    public function testAssociationConfigSaveInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
    }

    public function testAssociationConfigTestThreshold(): void
    {
        $testData = [
            'min_score' => 85.0,
            'test_data' => [
                'track1' => 90.0,
                'track2' => 70.0,
                'track3' => 95.0,
            ],
        ];

        $this->client->request(
            'POST',
            '/association-config/test-threshold',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('min_score', $responseData);
        $this->assertArrayHasKey('message', $responseData);
        $this->assertEquals(85.0, $responseData['min_score']);
    }

    public function testAssociationConfigReset(): void
    {
        // Create some test configuration
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        $this->client->request('POST', '/association-config/reset');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was reset to defaults
        $this->assertConfigurationExists('association.auto_association', true);
        $this->assertConfigurationExists('association.min_score', 85.0);
    }

    public function testAssociationConfigStatistics(): void
    {
        $this->client->request('GET', '/association-config/statistics');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
    }

    public function testAssociationConfigValidation(): void
    {
        $invalidData = [
            'min_score' => 150.0, // Invalid score (should be 0-100)
            'max_duration_difference' => -5, // Negative value
            'auto_association' => 'invalid', // Invalid boolean
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
    }

    public function testAssociationConfigComplexDataTypes(): void
    {
        $complexData = [
            'min_score' => 80.0,
            'auto_association' => true,
            'scoring_weights' => [
                'title' => 0.4,
                'artist' => 0.3,
                'album' => 0.2,
                'duration' => 0.1,
            ],
            'filters' => [
                'min_quality' => '320',
                'formats' => ['mp3', 'flac', 'aac'],
                'exclude_compilations' => true,
            ],
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($complexData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify complex data was properly serialized
        $this->assertConfigurationExists('association.auto_association', true);
        $this->assertConfigurationExists('association.min_score', 80.0);
    }

    public function testAssociationConfigDomainIntegration(): void
    {
        $associationDomain = $this->domainRegistry->getDomain('association.');

        if ($associationDomain) {
            $this->assertInstanceOf(AbstractConfigurationDomain::class, $associationDomain);

            // Test domain initialization
            $associationDomain->initializeDefaults();

            // Verify default values were created
            $this->assertTrue(null !== $this->getTestConfiguration('association.auto_association'));
        }
    }

    public function testAssociationConfigEventHandling(): void
    {
        // Test that configuration events are properly handled
        $testData = [
            'auto_association' => true,
            'min_score' => 75.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved (this tests the event handling)
        $this->assertConfigurationExists('association.auto_association', true);
        $this->assertConfigurationExists('association.min_score', 75.0);
    }

    public function testAssociationConfigConcurrentAccess(): void
    {
        // Test handling of concurrent configuration updates
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        // Simulate concurrent requests
        $data1 = ['auto_association' => false];
        $data2 = ['min_score' => 90.0];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data1)
        );

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data2)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify both updates were applied
        $this->assertConfigurationExists('association.auto_association', false);
        $this->assertConfigurationExists('association.min_score', 90.0);
    }

    public function testAssociationConfigBoundaryValues(): void
    {
        $boundaryData = [
            'min_score' => 0.0, // Minimum score
            'exact_artist_match' => false, // Boolean boundary
            'exact_album_match' => false, // Boolean boundary
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($boundaryData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boundary values were accepted
        $this->assertConfigurationExists('association.min_score', 0.0);
        $this->assertConfigurationExists('association.exact_artist_match', false);
        $this->assertConfigurationExists('association.exact_album_match', false);
    }

    public function testAssociationConfigThresholdValidation(): void
    {
        $testData = [
            'min_score' => 50.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify threshold was accepted
        $this->assertConfigurationExists('association.min_score', 50.0);
    }

    public function testAssociationConfigBooleanHandling(): void
    {
        $testData = [
            'auto_association' => false,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boolean values were properly converted
        $this->assertConfigurationExists('association.auto_association', false);
    }

    public function testAssociationConfigNumericHandling(): void
    {
        $testData = [
            'min_score' => 75.0,
        ];

        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify numeric values were properly converted
        $this->assertConfigurationExists('association.min_score', 75.0);
    }

    public function testAssociationConfigMethodValidation(): void
    {
        // Test that only POST is allowed for save
        $this->client->request('GET', '/association-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed

        $this->client->request('PUT', '/association-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed

        $this->client->request('DELETE', '/association-config/save');
        $this->assertResponseStatusCode(405); // Method not allowed
    }

    public function testAssociationConfigContentTypeValidation(): void
    {
        $testData = ['auto_association' => true];

        // Test without content type header
        $this->client->request(
            'POST',
            '/association-config/save',
            [],
            [],
            [],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Should still work without content type header
        $this->assertConfigurationExists('association.auto_association', true);
    }

    public function testAssociationConfigTestThresholdInvalidData(): void
    {
        $invalidData = [
            'min_score' => 150.0, // Invalid score (should be 0-100)
            'test_data' => 'invalid', // Invalid test data format
        ];

        $this->client->request(
            'POST',
            '/association-config/test-threshold',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCode(400);

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('success', $responseData);
        $this->assertFalse($responseData['success']);
    }

    public function testAssociationConfigResetConfirmation(): void
    {
        // Create some test configuration
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        // Test reset with confirmation
        $this->client->request(
            'POST',
            '/association-config/reset',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode(['confirm' => true])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was reset
        $this->assertConfigurationExists('association.auto_association', true);
        $this->assertConfigurationExists('association.min_score', 85.0);
    }

    public function testAssociationConfigStatisticsWithData(): void
    {
        // Create some test configuration and data
        $this->createTestConfiguration('association.auto_association', true);
        $this->createTestConfiguration('association.min_score', 80.0);

        $this->client->request('GET', '/association-config/statistics');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('total_configs', $responseData['data']);
    }
}
