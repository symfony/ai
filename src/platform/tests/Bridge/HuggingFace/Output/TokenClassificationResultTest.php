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

        $this->assertSame($tokens, $result->tokens);
        $this->assertCount(2, $result->tokens);
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new TokenClassificationResult([]);

        $this->assertSame([], $result->tokens);
        $this->assertCount(0, $result->tokens);
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

        $this->assertCount(1, $result1->tokens);

        $this->assertCount(3, $result2->tokens);
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

        $this->assertCount(3, $result->tokens);

        $this->assertSame('PERSON', $result->tokens[0]->entityGroup);
        $this->assertSame(0.95, $result->tokens[0]->score);
        $this->assertSame('John', $result->tokens[0]->word);
        $this->assertSame(0, $result->tokens[0]->start);
        $this->assertSame(4, $result->tokens[0]->end);

        $this->assertSame('ORG', $result->tokens[1]->entityGroup);
        $this->assertSame(0.87, $result->tokens[1]->score);
        $this->assertSame('Apple', $result->tokens[1]->word);
        $this->assertSame(10, $result->tokens[1]->start);
        $this->assertSame(15, $result->tokens[1]->end);

        $this->assertSame('LOC', $result->tokens[2]->entityGroup);
        $this->assertSame(0.92, $result->tokens[2]->score);
        $this->assertSame('Paris', $result->tokens[2]->word);
        $this->assertSame(20, $result->tokens[2]->start);
        $this->assertSame(25, $result->tokens[2]->end);
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = TokenClassificationResult::fromArray([]);

        $this->assertCount(0, $result->tokens);
        $this->assertSame([], $result->tokens);
    }

    #[TestDox('fromArray with single token')]
    public function testFromArrayWithSingleToken()
    {
        $data = [
            ['entity_group' => 'PERSON', 'score' => 0.99, 'word' => 'Alice', 'start' => 5, 'end' => 10],
        ];

        $result = TokenClassificationResult::fromArray($data);

        $this->assertCount(1, $result->tokens);
        $this->assertInstanceOf(Token::class, $result->tokens[0]);
        $this->assertSame('PERSON', $result->tokens[0]->entityGroup);
        $this->assertSame(0.99, $result->tokens[0]->score);
        $this->assertSame('Alice', $result->tokens[0]->word);
        $this->assertSame(5, $result->tokens[0]->start);
        $this->assertSame(10, $result->tokens[0]->end);
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

        $this->assertSame('FIRST', $result->tokens[0]->entityGroup);
        $this->assertSame('SECOND', $result->tokens[1]->entityGroup);
        $this->assertSame('THIRD', $result->tokens[2]->entityGroup);
    }

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

        $this->assertCount(1, $result->tokens);
        $this->assertSame($tokenData['entity_group'], $result->tokens[0]->entityGroup);
        $this->assertSame($tokenData['score'], $result->tokens[0]->score);
        $this->assertSame($tokenData['word'], $result->tokens[0]->word);
        $this->assertSame($tokenData['start'], $result->tokens[0]->start);
        $this->assertSame($tokenData['end'], $result->tokens[0]->end);
    }

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

        $this->assertCount(1, $result->tokens);
        $this->assertSame($tokenData['word'], $result->tokens[0]->word);
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

        $this->assertCount(4, $result->tokens);

        $this->assertSame(0.0, $result->tokens[0]->score);
        $this->assertSame(1.0, $result->tokens[1]->score);
        $this->assertSame(0.123456789, $result->tokens[2]->score);
        $this->assertSame(-0.1, $result->tokens[3]->score);

        $this->assertSame(-5, $result->tokens[3]->start);
        $this->assertSame(-1, $result->tokens[3]->end);
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

        $this->assertCount(100, $result->tokens);

        $this->assertSame('ENTITY_0', $result->tokens[0]->entityGroup);
        $this->assertSame(0.0, $result->tokens[0]->score);
        $this->assertSame('word_0', $result->tokens[0]->word);

        $this->assertSame('ENTITY_99', $result->tokens[99]->entityGroup);
        $this->assertSame(0.99, $result->tokens[99]->score);
        $this->assertSame('word_99', $result->tokens[99]->word);
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
        $this->assertInstanceOf(Token::class, $result->tokens[0]);
        $this->assertInstanceOf(Token::class, $result->tokens[1]);
        $this->assertNotSame($result->tokens[0], $result->tokens[1]);

        // Verify that the Token instances have the correct readonly properties
        $token1 = $result->tokens[0];
        $token2 = $result->tokens[1];

        $this->assertSame('PERSON', $token1->entityGroup);
        $this->assertSame(0.95, $token1->score);
        $this->assertSame('John', $token1->word);
        $this->assertSame(0, $token1->start);
        $this->assertSame(4, $token1->end);

        $this->assertSame('ORG', $token2->entityGroup);
        $this->assertSame(0.87, $token2->score);
        $this->assertSame('Apple', $token2->word);
        $this->assertSame(10, $token2->start);
        $this->assertSame(15, $token2->end);
    }
}
