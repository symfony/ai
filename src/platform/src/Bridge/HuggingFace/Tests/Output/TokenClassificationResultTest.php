<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Tests\Output;

use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\Token;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\TokenClassificationResult;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class TokenClassificationResultTest extends TestCase
{
    #[TestDox('Construction with tokens array creates valid instance')]
    public function testConstruction()
    {
        $tokens = [
            new Token('PERSON', 0.99, 'John', 0, 4),
            new Token('ORG', 0.87, 'Apple', 10, 15),
        ];

        $result = new TokenClassificationResult($tokens);

        $this->assertSame($tokens, $result->getTokens());
        $this->assertCount(2, $result->getTokens());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new TokenClassificationResult([]);

        $this->assertSame([], $result->getTokens());
        $this->assertCount(0, $result->getTokens());
    }

    #[TestDox('Constructor accepts various token arrays')]
    public function testConstructorWithDifferentArrays()
    {
        $singleToken = [new Token('PERSON', 0.95, 'Alice', 5, 10)];
        $multipleTokens = [
            new Token('PERSON', 0.95, 'John', 0, 4),
            new Token('ORG', 0.87, 'Microsoft', 10, 19),
            new Token('LOC', 0.92, 'Seattle', 25, 32),
        ];

        $result1 = new TokenClassificationResult($singleToken);
        $result2 = new TokenClassificationResult($multipleTokens);

        $this->assertCount(1, $result1->getTokens());

        $this->assertCount(3, $result2->getTokens());
    }
}
