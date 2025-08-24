<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\StringSimilarity;
use PHPUnit\Framework\TestCase;

class StringSimilarityTest extends TestCase
{
    private StringSimilarity $stringSimilarity;

    protected function setUp(): void
    {
        $this->stringSimilarity = new StringSimilarity();
    }

    public function testSimilarityWithIdenticalStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', 'hello');

        $this->assertEquals(1.0, $result);
    }

    public function testSimilarityWithCompletelyDifferentStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', 'world');

        $this->assertLessThan(0.3, $result);
    }

    public function testSimilarityWithSimilarStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', 'helo');

        $this->assertGreaterThanOrEqual(0.8, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithCaseDifference(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('Hello', 'hello');

        $this->assertEquals(1.0, $result);
    }

    public function testSimilarityWithWhitespaceDifference(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'helloworld');

        $this->assertGreaterThan(0.9, $result);
    }

    public function testSimilarityWithEmptyStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('', '');

        $this->assertEquals(1.0, $result);
    }

    public function testSimilarityWithOneEmptyString(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', '');

        $this->assertEquals(0.0, $result);
    }

    public function testSimilarityWithSpecialCharacters(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello-world', 'hello_world');

        $this->assertGreaterThan(0.8, $result);
    }

    public function testSimilarityWithNumbers(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello123', 'hello456');

        $this->assertGreaterThan(0.6, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithLongStrings(): void
    {
        $longString1 = 'This is a very long string that contains many words and should be compared with another long string';
        $longString2 = 'This is a very long string that contains many words and should be compared with another long string';

        $result = $this->stringSimilarity->calculateSimilarity($longString1, $longString2);

        $this->assertEquals(1.0, $result);
    }

    public function testSimilarityWithLongStringsWithDifferences(): void
    {
        $longString1 = 'This is a very long string that contains many words and should be compared with another long string';
        $longString2 = 'This is a very long string that contains many words and should be compared with another different string';

        $result = $this->stringSimilarity->calculateSimilarity($longString1, $longString2);

        $this->assertGreaterThan(0.8, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithUnicodeCharacters(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('café', 'cafe');

        $this->assertGreaterThan(0.5, $result);
    }

    public function testSimilarityWithAccentedCharacters(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('résumé', 'resume');

        $this->assertGreaterThan(0.4, $result);
    }

    public function testSimilarityWithPunctuation(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello, world!', 'hello world');

        $this->assertGreaterThan(0.8, $result);
    }

    public function testSimilarityWithReversedStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'world hello');

        $this->assertGreaterThan(0.0, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithPartialMatch(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'hello');

        $this->assertGreaterThan(0.4, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithExtraWords(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'hello world extra');

        $this->assertGreaterThan(0.6, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithSingleCharacterDifference(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', 'helo');

        $this->assertGreaterThanOrEqual(0.8, $result);
    }

    public function testSimilarityWithMultipleCharacterDifferences(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'helo wrld');

        $this->assertGreaterThan(0.7, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithVerySimilarStrings(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello world', 'hello worl');

        $this->assertGreaterThan(0.9, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithCompletelyDifferentLengths(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('a', 'abcdefghijklmnopqrstuvwxyz');

        $this->assertLessThan(0.1, $result);
    }

    public function testSimilarityWithRepeatedCharacters(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello', 'helllo');

        $this->assertGreaterThan(0.8, $result);
        $this->assertLessThan(1.0, $result);
    }

    public function testSimilarityWithMixedCaseAndPunctuation(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('Hello, World!', 'hello world');

        $this->assertGreaterThan(0.8, $result);
    }

    public function testSimilarityWithSpacesAtEnds(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity(' hello ', 'hello');

        $this->assertEquals(1.0, $result);
    }

    public function testSimilarityWithMultipleSpaces(): void
    {
        $result = $this->stringSimilarity->calculateSimilarity('hello  world', 'hello world');

        $this->assertGreaterThan(0.9, $result);
    }

    public function testCalculateNormalizedSimilarity(): void
    {
        $result = $this->stringSimilarity->calculateNormalizedSimilarity('Hello, World!', 'hello world');

        $this->assertGreaterThan(0.9, $result);
    }

    public function testIsSimilarWithHighThreshold(): void
    {
        $result = $this->stringSimilarity->isSimilar('hello', 'helo', 0.8);

        $this->assertTrue($result);
    }

    public function testIsSimilarWithLowThreshold(): void
    {
        $result = $this->stringSimilarity->isSimilar('hello', 'world', 0.8);

        $this->assertFalse($result);
    }

    public function testFindBestMatch(): void
    {
        $candidates = ['hello', 'world', 'help', 'hell'];
        $result = $this->stringSimilarity->findBestMatch('hello', $candidates);

        $this->assertNotNull($result);
        $this->assertEquals('hello', $result['match']);
        $this->assertEquals(1.0, $result['score']);
    }

    public function testFindBestMatchWithNoExactMatch(): void
    {
        $candidates = ['world', 'help', 'hell'];
        $result = $this->stringSimilarity->findBestMatch('hello', $candidates);

        $this->assertNotNull($result);
        $this->assertContains($result['match'], $candidates);
        $this->assertGreaterThan(0.0, $result['score']);
    }

    public function testFindBestMatchWithEmptyCandidates(): void
    {
        $result = $this->stringSimilarity->findBestMatch('hello', []);

        $this->assertNull($result);
    }

    public function testNormalizeApostrophes(): void
    {
        $result = $this->stringSimilarity->normalizeApostrophes("Don't");

        $this->assertEquals("Don't", $result);
    }

    public function testNormalizeApostrophesWithNull(): void
    {
        $result = $this->stringSimilarity->normalizeApostrophes(null);

        $this->assertNull($result);
    }
}
