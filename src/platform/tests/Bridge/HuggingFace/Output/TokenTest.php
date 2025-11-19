<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\HuggingFace\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Token;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class TokenTest extends TestCase
{
    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstruction()
    {
        $token = new Token('PERSON', 0.99, 'John', 0, 4);

        $this->assertSame('PERSON', $token->entityGroup);
        $this->assertSame(0.99, $token->score);
        $this->assertSame('John', $token->word);
        $this->assertSame(0, $token->start);
        $this->assertSame(4, $token->end);
    }

    #[TestDox('Constructor accepts various parameter combinations')]
    #[TestWith(['PERSON', 0.95, 'Alice', 10, 15])]
    #[TestWith(['ORG', 0.87, 'Microsoft', 20, 29])]
    #[TestWith(['LOC', 0.92, 'Paris', 35, 40])]
    #[TestWith(['MISC', 0.76, 'Python', 50, 56])]
    #[TestWith(['', 0.0, '', 0, 0])] // Edge case with empty values
    #[TestWith(['CUSTOM_ENTITY', 1.0, 'special-word_123', 100, 116])]
    #[TestWith(['O', 0.01, 'the', 5, 8])] // Outside entity
    public function testConstructorWithDifferentValues(string $entityGroup, float $score, string $word, int $start, int $end)
    {
        $token = new Token($entityGroup, $score, $word, $start, $end);

        $this->assertSame($entityGroup, $token->entityGroup);
        $this->assertSame($score, $token->score);
        $this->assertSame($word, $token->word);
        $this->assertSame($start, $token->start);
        $this->assertSame($end, $token->end);
    }

    #[TestDox('Constructor handles various token patterns')]
    #[TestWith(['I-PERSON', 0.95, 'John', 0, 4])] // BIO tagging
    #[TestWith(['B-ORG', 0.87, 'Apple', 10, 15])] // BIO tagging
    #[TestWith(['PERSON', 0.9, 'O\'Connor', 0, 8])] // Apostrophe in word
    #[TestWith(['ORG', 0.85, 'AT&T', 10, 14])] // Special characters
    #[TestWith(['LOC', 0.92, 'New York', 20, 28])] // Space in word
    #[TestWith(['MISC', 0.78, '2023', 30, 34])] // Numeric word
    #[TestWith(['PERSON', 0.95, 'José', 40, 44])] // Accented characters
    #[TestWith(['LOC', 0.82, '北京', 70, 72])] // Non-Latin script
    #[TestWith(['MISC', 0.0, 'zero', 30, 34])] // Zero confidence
    #[TestWith(['PERSON', 1.0, 'perfect', 40, 47])] // Perfect confidence
    #[TestWith(['PERSON', 0.9, 'start', 0, 5])] // Zero start position
    #[TestWith(['ORG', 0.8, 'large', 1000, 1005])] // Large positions
    public function testConstructorHandlesVariousTokenPatterns(string $entityGroup, float $score, string $word, int $start, int $end)
    {
        $token = new Token($entityGroup, $score, $word, $start, $end);

        $this->assertSame($entityGroup, $token->entityGroup);
        $this->assertSame($score, $token->score);
        $this->assertSame($word, $token->word);
        $this->assertSame($start, $token->start);
        $this->assertSame($end, $token->end);
    }
}
