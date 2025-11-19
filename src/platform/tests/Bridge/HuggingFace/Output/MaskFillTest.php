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
use Symfony\AI\Platform\Bridge\HuggingFace\Output\MaskFill;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class MaskFillTest extends TestCase
{
    #[TestDox('Construction with all parameters creates valid instance')]
    public function testConstruction()
    {
        $maskFill = new MaskFill(
            token: 12345,
            tokenStr: 'happy',
            sequence: 'I am feeling happy today',
            score: 0.85
        );

        $this->assertSame(12345, $maskFill->token);
        $this->assertSame('happy', $maskFill->tokenStr);
        $this->assertSame('I am feeling happy today', $maskFill->sequence);
        $this->assertSame(0.85, $maskFill->score);
    }

    #[TestDox('Constructor accepts various token IDs')]
    #[TestWith([0, 'word', 'The word is here', 0.5])]
    #[TestWith([1, 'token', 'A token string', 0.7])]
    #[TestWith([999999, 'large', 'Large token ID', 0.9])]
    #[TestWith([-1, 'negative', 'Negative token ID', 0.3])]
    public function testConstructorWithVariousTokenIds(
        int $token,
        string $tokenStr,
        string $sequence,
        float $score,
    ) {
        $maskFill = new MaskFill($token, $tokenStr, $sequence, $score);

        $this->assertSame($token, $maskFill->token);
        $this->assertSame($tokenStr, $maskFill->tokenStr);
        $this->assertSame($sequence, $maskFill->sequence);
        $this->assertSame($score, $maskFill->score);
    }

    #[TestDox('Constructor handles various token strings')]
    #[TestWith([''])]
    #[TestWith([' '])]
    #[TestWith(['word'])]
    #[TestWith(['UPPERCASE'])]
    #[TestWith(['with-dash'])]
    #[TestWith(['with_underscore'])]
    #[TestWith(['123numeric'])]
    #[TestWith(['spécial'])]
    #[TestWith(['emoji😊'])]
    #[TestWith(['very_long_token_string_that_might_appear_in_some_models'])]
    public function testConstructorWithVariousTokenStrings(string $tokenStr)
    {
        $maskFill = new MaskFill(100, $tokenStr, 'Test sequence', 0.5);
        $this->assertSame($tokenStr, $maskFill->tokenStr);
    }

    #[TestDox('Constructor handles various sequences')]
    #[TestWith([''])]
    #[TestWith(['Short'])]
    #[TestWith(['A normal sentence with multiple words.'])]
    #[TestWith(['Sentence with special characters: !@#$%^&*()'])]
    #[TestWith(["Multi-line\nsentence\nwith\nbreaks"])]
    #[TestWith(['Unicode: 你好世界 مرحبا بالعالم'])]
    #[TestWith(['Very long long long long long long long long long long long long long long long long long long long long sequence'])]
    public function testConstructorWithVariousSequences(string $sequence)
    {
        $maskFill = new MaskFill(100, 'token', $sequence, 0.5);
        $this->assertSame($sequence, $maskFill->sequence);
    }

    #[TestDox('Constructor handles edge case scores')]
    #[TestWith([0.0])]
    #[TestWith([0.000001])]
    #[TestWith([0.5])]
    #[TestWith([0.999999])]
    #[TestWith([1.0])]
    public function testConstructorWithEdgeScores(float $score)
    {
        $maskFill = new MaskFill(100, 'token', 'sequence', $score);
        $this->assertSame($score, $maskFill->score);
    }
}
