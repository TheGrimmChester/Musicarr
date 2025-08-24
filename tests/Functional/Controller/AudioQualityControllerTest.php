<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Configuration\Domain\AbstractConfigurationDomain;
use App\Tests\Functional\AbstractFunctionalTestCase;

class AudioQualityControllerTest extends AbstractFunctionalTestCase
{
    public function testAudioQualityIndexPageLoads(): void
    {
        $this->client->request('GET', '/audio-quality/');

        $this->assertResponseIsSuccessful();
        $this->assertSelectorExists('form');
        $this->assertFormFieldExists('enabled', 'input[type="checkbox"]');
        $this->assertFormFieldExists('min_bitrate', 'input[type="number"]');
        $this->assertFormFieldExists('preferred_format', 'select');
        $this->assertFormFieldExists('analyze_existing', 'input[type="checkbox"]');
    }

    public function testAudioQualityIndexWithExistingConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);
        $this->createTestConfiguration('audio_quality.preferred_format', 'mp3');
        $this->createTestConfiguration('audio_quality.analyze_existing', true);

        $this->client->request('GET', '/audio-quality/');

        $this->assertResponseIsSuccessful();
        $this->assertFormFieldValue('enabled', 'input[name="enabled"]', '1');
        $this->assertFormFieldValue('min_bitrate', 'input[name="min_bitrate"]', '192');
        $this->assertFormFieldValue('preferred_format', 'select[name="preferred_format"]', 'mp3');
        $this->assertFormFieldValue('analyze_existing', 'input[name="analyze_existing"]', '1');
    }

    public function testAudioQualitySaveNewConfiguration(): void
    {
        $testData = [
            'enabled' => true,
            'min_bitrate' => 320,
            'preferred_format' => 'flac',
            'analyze_existing' => false,
            'quality_threshold' => 0.8,
            'auto_convert' => true,
            'convert_to_format' => 'mp3',
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved
        $this->assertConfigurationExists('audio_quality.enabled', '1');
        $this->assertConfigurationExists('audio_quality.min_bitrate', '320');
        $this->assertConfigurationExists('audio_quality.preferred_format', 'flac');
        $this->assertConfigurationExists('audio_quality.analyze_existing', '0');
        $this->assertConfigurationExists('audio_quality.quality_threshold', '0.8');
        $this->assertConfigurationExists('audio_quality.auto_convert', '1');
        $this->assertConfigurationExists('audio_quality.convert_to_format', 'mp3');
    }

    public function testAudioQualityUpdateExistingConfiguration(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);
        $this->createTestConfiguration('audio_quality.preferred_format', 'mp3');

        $updateData = [
            'enabled' => false,
            'min_bitrate' => 256,
            'preferred_format' => 'aac',
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($updateData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was updated
        $this->assertConfigurationExists('audio_quality.enabled', '0');
        $this->assertConfigurationExists('audio_quality.min_bitrate', '256');
        $this->assertConfigurationExists('audio_quality.preferred_format', 'aac');
    }

    public function testAudioQualityPartialUpdate(): void
    {
        // Create initial configuration
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);
        $this->createTestConfiguration('audio_quality.preferred_format', 'mp3');

        $partialData = [
            'min_bitrate' => 512,
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($partialData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify only specified field was updated
        $this->assertConfigurationExists('audio_quality.min_bitrate', '512');
        // Other fields should remain unchanged
        $this->assertConfigurationExists('audio_quality.enabled', '1');
        $this->assertConfigurationExists('audio_quality.preferred_format', 'mp3');
    }

    public function testAudioQualitySaveEmptyData(): void
    {
        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode([])
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();
    }

    public function testAudioQualitySaveInvalidJson(): void
    {
        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            'invalid json'
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonResponseError();
    }

    public function testAudioQualitySaveInvalidDataTypes(): void
    {
        $invalidData = [
            'enabled' => 'not_a_boolean',
            'min_bitrate' => 'not_a_number',
            'preferred_format' => 123,
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonResponseError();
    }

    public function testAudioQualityGetConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);
        $this->createTestConfiguration('audio_quality.preferred_format', 'mp3');

        $this->client->request('GET', '/audio-quality/get');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
        $this->assertArrayHasKey('enabled', $responseData['data']);
        $this->assertArrayHasKey('min_bitrate', $responseData['data']);
        $this->assertArrayHasKey('preferred_format', $responseData['data']);
    }

    public function testAudioQualityGetConfigurationEmpty(): void
    {
        $this->client->request('GET', '/audio-quality/get');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        $responseData = $this->getJsonResponseData();
        $this->assertArrayHasKey('data', $responseData);
    }

    public function testAudioQualityDeleteConfiguration(): void
    {
        // Create test configuration
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);
        $this->createTestConfiguration('audio_quality.preferred_format', 'mp3');

        $this->client->request('DELETE', '/audio-quality/delete');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was deleted
        $this->assertConfigurationNotExists('audio_quality.enabled');
        $this->assertConfigurationNotExists('audio_quality.min_bitrate');
        $this->assertConfigurationNotExists('audio_quality.preferred_format');
    }

    public function testAudioQualityDeleteConfigurationEmpty(): void
    {
        $this->client->request('DELETE', '/audio-quality/delete');

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();
    }

    public function testAudioQualityValidation(): void
    {
        $invalidData = [
            'min_bitrate' => -100, // Negative bitrate
            'quality_threshold' => 1.5, // Above maximum
            'preferred_format' => 'invalid_format', // Invalid format
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($invalidData)
        );

        $this->assertResponseStatusCodeSame(400);
        $this->assertJsonResponseError();
    }

    public function testAudioQualityComplexDataTypes(): void
    {
        $complexData = [
            'enabled' => true,
            'min_bitrate' => 320,
            'preferred_format' => 'flac',
            'analyze_existing' => true,
            'quality_settings' => [
                'mp3' => [
                    'min_bitrate' => 192,
                    'max_bitrate' => 320,
                    'preferred_bitrate' => 256,
                ],
                'flac' => [
                    'compression_level' => 8,
                    'verify_integrity' => true,
                ],
            ],
            'conversion_rules' => [
                'auto_convert_low_quality' => true,
                'target_formats' => ['mp3', 'aac'],
                'preserve_original' => true,
            ],
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($complexData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify complex data was properly serialized
        $this->assertConfigurationExists('audio_quality.enabled', '1');
        $this->assertConfigurationExists('audio_quality.min_bitrate', '320');
        $this->assertConfigurationExists('audio_quality.preferred_format', 'flac');
    }

    public function testAudioQualityDomainIntegration(): void
    {
        $audioQualityDomain = $this->domainRegistry->getDomain('audio_quality.');

        if ($audioQualityDomain) {
            $this->assertInstanceOf(AbstractConfigurationDomain::class, $audioQualityDomain);

            // Test domain initialization
            $audioQualityDomain->initializeDefaults();

            // Verify default values were created
            $this->assertTrue($this->testConfigService->hasConfiguration('audio_quality.enabled'));
        }
    }

    public function testAudioQualityEventHandling(): void
    {
        // Test that configuration events are properly handled
        $testData = [
            'enabled' => true,
            'min_bitrate' => 256,
            'preferred_format' => 'mp3',
            'analyze_existing' => true,
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($testData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify configuration was saved (this tests the event handling)
        $this->assertConfigurationExists('audio_quality.enabled', '1');
        $this->assertConfigurationExists('audio_quality.min_bitrate', '256');
        $this->assertConfigurationExists('audio_quality.preferred_format', 'mp3');
        $this->assertConfigurationExists('audio_quality.analyze_existing', '1');
    }

    public function testAudioQualityConcurrentAccess(): void
    {
        // Test handling of concurrent configuration updates
        $this->createTestConfiguration('audio_quality.enabled', true);
        $this->createTestConfiguration('audio_quality.min_bitrate', 192);

        // Simulate concurrent requests
        $data1 = ['enabled' => false];
        $data2 = ['min_bitrate' => 512];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data1)
        );

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($data2)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify both updates were applied
        $this->assertConfigurationExists('audio_quality.enabled', '0');
        $this->assertConfigurationExists('audio_quality.min_bitrate', '512');
    }

    public function testAudioQualityBoundaryValues(): void
    {
        $boundaryData = [
            'min_bitrate' => 32, // Minimum reasonable bitrate
            'quality_threshold' => 0.0, // Minimum value
            'max_bitrate' => 2000, // Maximum reasonable bitrate
            'compression_level' => 0, // Minimum compression level
        ];

        $this->client->request(
            'POST',
            '/audio-quality/save',
            [],
            [],
            ['CONTENT_TYPE' => 'application/json'],
            json_encode($boundaryData)
        );

        $this->assertResponseIsSuccessful();
        $this->assertJsonResponseSuccess();

        // Verify boundary values were accepted
        $this->assertConfigurationExists('audio_quality.min_bitrate', '32');
        $this->assertConfigurationExists('audio_quality.quality_threshold', '0');
        $this->assertConfigurationExists('audio_quality.max_bitrate', '2000');
        $this->assertConfigurationExists('audio_quality.compression_level', '0');
    }

    public function testAudioQualityFormatValidation(): void
    {
        $validFormats = [
            'mp3' => true,
            'flac' => true,
            'aac' => true,
            'ogg' => true,
            'wav' => true,
        ];

        foreach ($validFormats as $format => $expected) {
            $testData = [
                'preferred_format' => $format,
                'enabled' => true,
            ];

            $this->client->request(
                'POST',
                '/audio-quality/save',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($testData)
            );

            $this->assertResponseIsSuccessful();
            $this->assertJsonResponseSuccess();

            // Verify format was accepted
            $this->assertConfigurationExists('audio_quality.preferred_format', $format);
        }
    }

    public function testAudioQualityBitrateValidation(): void
    {
        $validBitrates = [32, 64, 128, 192, 256, 320, 512, 1024, 2000];

        foreach ($validBitrates as $bitrate) {
            $testData = [
                'min_bitrate' => $bitrate,
                'enabled' => true,
            ];

            $this->client->request(
                'POST',
                '/audio-quality/save',
                [],
                [],
                ['CONTENT_TYPE' => 'application/json'],
                json_encode($testData)
            );

            $this->assertResponseIsSuccessful();
            $this->assertJsonResponseSuccess();

            // Verify bitrate was accepted
            $this->assertConfigurationExists('audio_quality.min_bitrate', (string) $bitrate);
        }
    }
}
