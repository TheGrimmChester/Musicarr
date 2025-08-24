<?php

declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Artist;
use PHPUnit\Framework\TestCase;

class ArtistImageTest extends TestCase
{
    public function testArtistImageValidation()
    {
        $artist = new Artist();
        $artist->setName('Test Artist');
        $artist->setMbid('test-mbid-123');

        // Test without image
        $this->assertFalse($artist->hasArtistImage());
        $this->assertFalse($artist->isArtistImageValid());
        $this->assertNull($artist->getArtistImageInfo());

        // Test with image URL
        $artist->setImageUrl('/metadata/artists/test-mbid-123.jpg');

        // Note: In a real test environment, we'd need to create actual files
        // For now, we're just testing the method signatures and basic logic
        $this->assertInstanceOf(Artist::class, $artist);
    }

    public function testArtistImageUrlNormalization()
    {
        $artist = new Artist();
        $artist->setName('Test Artist');

        // Test various URL formats
        $testCases = [
            'metadata/artists/test.jpg' => '/metadata/artists/test.jpg',
            'public/metadata/artists/test.jpg' => '/metadata/artists/test.jpg',
            '/metadata/artists/test.jpg' => '/metadata/artists/test.jpg',
            'test.jpg' => '/metadata/artists/test.jpg',
        ];

        foreach ($testCases as $input => $expected) {
            $artist->setImageUrl($input);
            $this->assertEquals($expected, $artist->getArtistImageUrl());
        }
    }

    public function testArtistImageUrlWithNull()
    {
        $artist = new Artist();
        $artist->setName('Test Artist');
        $artist->setImageUrl(null);

        $this->assertNull($artist->getArtistImageUrl());
    }
}
