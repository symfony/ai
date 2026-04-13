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
use PHPUnit\Framework\Attributes\TestWith;
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

    #[TestDox('fromArray creates instance with Token objects')]
    public function testFromArray()
    {
        $data = [
            ['entity_group' => 'PERSON', 'score' => 0.95, 'word' => 'John', 'start' => 0, 'end' => 4],
            ['entity_group' => 'ORG', 'score' => 0.87, 'word' => 'Apple', 'start' => 10, 'end' => 15],
            ['entity_group' => 'LOC', 'score' => 0.92, 'word' => 'Paris', 'start' => 20, 'end' => 25],
        ];

        $result = TokenClassificationResult::fromArray($data);

        $this->assertCount(3, $result->getTokens());

        $this->assertSame('PERSON', $result->getTokens()[0]->getEntityGroup());
        $this->assertSame(0.95, $result->getTokens()[0]->getScore());
        $this->assertSame('John', $result->getTokens()[0]->getWord());
        $this->assertSame(0, $result->getTokens()[0]->getStart());
        $this->assertSame(4, $result->getTokens()[0]->getEnd());

        $this->assertSame('ORG', $result->getTokens()[1]->getEntityGroup());
        $this->assertSame(0.87, $result->getTokens()[1]->getScore());
        $this->assertSame('Apple', $result->getTokens()[1]->getWord());
        $this->assertSame(10, $result->getTokens()[1]->getStart());
        $this->assertSame(15, $result->getTokens()[1]->getEnd());

        $this->assertSame('LOC', $result->getTokens()[2]->getEntityGroup());
        $this->assertSame(0.92, $result->getTokens()[2]->getScore());
        $this->assertSame('Paris', $result->getTokens()[2]->getWord());
        $this->assertSame(20, $result->getTokens()[2]->getStart());
        $this->assertSame(25, $result->getTokens()[2]->getEnd());
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = TokenClassificationResult::fromArray([]);

        $this->assertCount(0, $result->getTokens());
        $this->assertSame([], $result->getTokens());
    }

    #[TestDox('fromArray with single token')]
    public function testFromArrayWithSingleToken()
    {
        $data = [
            ['entity_group' => 'PERSON', 'score' => 0.99, 'word' => 'Alice', 'start' => 5, 'end' => 10],
        ];

        $result = TokenClassificationResult::fromArray($data);

        $this->assertCount(1, $result->getTokens());
        $this->assertInstanceOf(Token::class, $result->getTokens()[0]);
        $this->assertSame('PERSON', $result->getTokens()[0]->getEntityGroup());
        $this->assertSame(0.99, $result->getTokens()[0]->getScore());
        $this->assertSame('Alice', $result->getTokens()[0]->getWord());
        $this->assertSame(5, $result->getTokens()[0]->getStart());
        $this->assertSame(10, $result->getTokens()[0]->getEnd());
    }

    #[TestDox('fromArray preserves order of tokens')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['entity_group' => 'FIRST', 'score' => 0.5, 'word' => 'first', 'start' => 0, 'end' => 5],
            ['entity_group' => 'SECOND', 'score' => 0.3, 'word' => 'second', 'start' => 6, 'end' => 12],
            ['entity_group' => 'THIRD', 'score' => 0.2, 'word' => 'third', 'start' => 13, 'end' => 18],
        ];

        $result = TokenClassificationResult::fromArray($data);

        $this->assertSame('FIRST', $result->getTokens()[0]->getEntityGroup());
        $this->assertSame('SECOND', $result->getTokens()[1]->getEntityGroup());
        $this->assertSame('THIRD', $result->getTokens()[2]->getEntityGroup());
    }

    /**
     * @param array{entity_group: string, score: float, word: string, start: int, end: int} $tokenData
     */
    #[TestDox('fromArray handles various entity group formats')]
    #[TestWith([['entity_group' => '', 'score' => 0.5, 'word' => 'empty', 'start' => 0, 'end' => 5]])]
    #[TestWith([['entity_group' => 'UPPERCASE', 'score' => 0.5, 'word' => 'test', 'start' => 0, 'end' => 4]])]
    #[TestWith([['entity_group' => 'lowercase', 'score' => 0.5, 'word' => 'test', 'start' => 0, 'end' => 4]])]
    #[TestWith([['entity_group' => 'Mixed_Case-Entity', 'score' => 0.5, 'word' => 'test', 'start' => 0, 'end' => 4]])]
    #[TestWith([['entity_group' => 'B-PERSON', 'score' => 0.5, 'word' => 'John', 'start' => 0, 'end' => 4]])]
    #[TestWith([['entity_group' => 'I-ORG', 'score' => 0.5, 'word' => 'Corp', 'start' => 5, 'end' => 9]])]
    public function testFromArrayWithVariousEntityGroups(array $tokenData)
    {
        $result = TokenClassificationResult::fromArray([$tokenData]);

        $this->assertCount(1, $result->getTokens());
        $this->assertSame($tokenData['entity_group'], $result->getTokens()[0]->getEntityGroup());
        $this->assertSame($tokenData['score'], $result->getTokens()[0]->getScore());
        $this->assertSame($tokenData['word'], $result->getTokens()[0]->getWord());
        $this->assertSame($tokenData['start'], $result->getTokens()[0]->getStart());
        $this->assertSame($tokenData['end'], $result->getTokens()[0]->getEnd());
    }

    /**
     * @param array{entity_group: string, score: float, word: string, start: int, end: int} $tokenData
     */
    #[TestDox('fromArray handles various word formats')]
    #[TestWith([['entity_group' => 'PERSON', 'score' => 0.9, 'word' => '', 'start' => 0, 'end' => 0]])]
    #[TestWith([['entity_group' => 'PERSON', 'score' => 0.9, 'word' => 'O\'Connor', 'start' => 0, 'end' => 8]])]
    #[TestWith([['entity_group' => 'ORG', 'score' => 0.9, 'word' => 'AT&T', 'start' => 10, 'end' => 14]])]
    #[TestWith([['entity_group' => 'MISC', 'score' => 0.9, 'word' => '2023', 'start' => 20, 'end' => 24]])]
    #[TestWith([['entity_group' => 'PERSON', 'score' => 0.9, 'word' => 'José', 'start' => 30, 'end' => 34]])]
    #[TestWith([['entity_group' => 'LOC', 'score' => 0.9, 'word' => 'New York', 'start' => 40, 'end' => 48]])]
    public function testFromArrayWithVariousWordFormats(array $tokenData)
    {
        $result = TokenClassificationResult::fromArray([$tokenData]);

        $this->assertCount(1, $result->getTokens());
        $this->assertSame($tokenData['word'], $result->getTokens()[0]->getWord());
    }

    #[TestDox('fromArray handles edge cases for scores and positions')]
    public function testFromArrayWithEdgeCases()
    {
        $data = [
            ['entity_group' => 'TEST', 'score' => 0.0, 'word' => 'zero', 'start' => 0, 'end' => 4],
            ['entity_group' => 'TEST', 'score' => 1.0, 'word' => 'one', 'start' => 5, 'end' => 8],
            ['entity_group' => 'TEST', 'score' => 0.123456789, 'word' => 'precise', 'start' => 10, 'end' => 17],
            ['entity_group' => 'TEST', 'score' => -0.1, 'word' => 'negative', 'start' => -5, 'end' => -1],
        ];

        $result = TokenClassificationResult::fromArray($data);

        $this->assertCount(4, $result->getTokens());

        $this->assertSame(0.0, $result->getTokens()[0]->getScore());
        $this->assertSame(1.0, $result->getTokens()[1]->getScore());
        $this->assertSame(0.123456789, $result->getTokens()[2]->getScore());
        $this->assertSame(-0.1, $result->getTokens()[3]->getScore());

        $this->assertSame(-5, $result->getTokens()[3]->getStart());
        $this->assertSame(-1, $result->getTokens()[3]->getEnd());
    }

    #[TestDox('Large token arrays are handled correctly')]
    public function testLargeTokenArrays()
    {
        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data[] = [
                'entity_group' => "ENTITY_$i",
                'score' => $i / 100.0,
                'word' => "word_$i",
                'start' => $i * 10,
                'end' => ($i * 10) + 5,
            ];
        }

        $result = TokenClassificationResult::fromArray($data);

        $this->assertCount(100, $result->getTokens());

        $this->assertSame('ENTITY_0', $result->getTokens()[0]->getEntityGroup());
        $this->assertSame(0.0, $result->getTokens()[0]->getScore());
        $this->assertSame('word_0', $result->getTokens()[0]->getWord());

        $this->assertSame('ENTITY_99', $result->getTokens()[99]->getEntityGroup());
        $this->assertSame(0.99, $result->getTokens()[99]->getScore());
        $this->assertSame('word_99', $result->getTokens()[99]->getWord());
    }

    #[TestDox('fromArray creates new Token instances correctly')]
    public function testFromArrayCreatesNewTokenInstances()
    {
        $data = [
            ['entity_group' => 'PERSON', 'score' => 0.95, 'word' => 'John', 'start' => 0, 'end' => 4],
            ['entity_group' => 'ORG', 'score' => 0.87, 'word' => 'Apple', 'start' => 10, 'end' => 15],
        ];

        $result = TokenClassificationResult::fromArray($data);

        // Each token should be a distinct Token instance
        $this->assertInstanceOf(Token::class, $result->getTokens()[0]);
        $this->assertInstanceOf(Token::class, $result->getTokens()[1]);
        $this->assertNotSame($result->getTokens()[0], $result->getTokens()[1]);

        // Verify that the Token instances have the correct readonly properties
        $token1 = $result->getTokens()[0];
        $token2 = $result->getTokens()[1];

        $this->assertSame('PERSON', $token1->getEntityGroup());
        $this->assertSame(0.95, $token1->getScore());
        $this->assertSame('John', $token1->getWord());
        $this->assertSame(0, $token1->getStart());
        $this->assertSame(4, $token1->getEnd());

        $this->assertSame('ORG', $token2->getEntityGroup());
        $this->assertSame(0.87, $token2->getScore());
        $this->assertSame('Apple', $token2->getWord());
        $this->assertSame(10, $token2->getStart());
        $this->assertSame(15, $token2->getEnd());
    }
}
