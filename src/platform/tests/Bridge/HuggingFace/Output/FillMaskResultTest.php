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
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\FillMaskResult;
use Symfony\AI\Platform\Bridge\HuggingFace\Output\MaskFill;

/**
 * @author Oskar Stark <oskar.stark@gmail.com>
 */
final class FillMaskResultTest extends TestCase
{
    #[TestDox('Construction with fills array creates valid instance')]
    public function testConstruction()
    {
        $fills = [
            new MaskFill(100, 'happy', 'I am happy', 0.9),
            new MaskFill(200, 'sad', 'I am sad', 0.05),
            new MaskFill(300, 'excited', 'I am excited', 0.05),
        ];

        $result = new FillMaskResult($fills);

        $this->assertSame($fills, $result->fills);
        $this->assertCount(3, $result->fills);
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new FillMaskResult([]);

        $this->assertSame([], $result->fills);
        $this->assertCount(0, $result->fills);
    }

    #[TestDox('fromArray creates instance with MaskFill objects')]
    public function testFromArray()
    {
        $data = [
            [
                'token' => 1234,
                'token_str' => 'happy',
                'sequence' => 'I feel happy today',
                'score' => 0.95,
            ],
            [
                'token' => 5678,
                'token_str' => 'great',
                'sequence' => 'I feel great today',
                'score' => 0.03,
            ],
            [
                'token' => 9012,
                'token_str' => 'wonderful',
                'sequence' => 'I feel wonderful today',
                'score' => 0.02,
            ],
        ];

        $result = FillMaskResult::fromArray($data);

        $this->assertCount(3, $result->fills);

        $this->assertSame(1234, $result->fills[0]->token);
        $this->assertSame('happy', $result->fills[0]->tokenStr);
        $this->assertSame('I feel happy today', $result->fills[0]->sequence);
        $this->assertSame(0.95, $result->fills[0]->score);

        $this->assertSame(5678, $result->fills[1]->token);
        $this->assertSame('great', $result->fills[1]->tokenStr);
        $this->assertSame('I feel great today', $result->fills[1]->sequence);
        $this->assertSame(0.03, $result->fills[1]->score);

        $this->assertSame(9012, $result->fills[2]->token);
        $this->assertSame('wonderful', $result->fills[2]->tokenStr);
        $this->assertSame('I feel wonderful today', $result->fills[2]->sequence);
        $this->assertSame(0.02, $result->fills[2]->score);
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = FillMaskResult::fromArray([]);

        $this->assertCount(0, $result->fills);
        $this->assertSame([], $result->fills);
    }

    #[TestDox('fromArray with single mask fill')]
    public function testFromArrayWithSingleFill()
    {
        $data = [
            [
                'token' => 999,
                'token_str' => 'word',
                'sequence' => 'The word is here',
                'score' => 0.99,
            ],
        ];

        $result = FillMaskResult::fromArray($data);

        $this->assertCount(1, $result->fills);
        $this->assertInstanceOf(MaskFill::class, $result->fills[0]);
        $this->assertSame(999, $result->fills[0]->token);
        $this->assertSame('word', $result->fills[0]->tokenStr);
        $this->assertSame('The word is here', $result->fills[0]->sequence);
        $this->assertSame(0.99, $result->fills[0]->score);
    }

    #[TestDox('fromArray preserves order of fills')]
    public function testFromArrayPreservesOrder()
    {
        $data = [
            ['token' => 1, 'token_str' => 'first', 'sequence' => 'First sequence', 'score' => 0.5],
            ['token' => 2, 'token_str' => 'second', 'sequence' => 'Second sequence', 'score' => 0.3],
            ['token' => 3, 'token_str' => 'third', 'sequence' => 'Third sequence', 'score' => 0.2],
        ];

        $result = FillMaskResult::fromArray($data);

        $this->assertSame('first', $result->fills[0]->tokenStr);
        $this->assertSame('second', $result->fills[1]->tokenStr);
        $this->assertSame('third', $result->fills[2]->tokenStr);
    }

    #[TestDox('fromArray handles various data formats')]
    public function testFromArrayWithVariousFormats()
    {
        $data = [
            [
                'token' => 0,
                'token_str' => '',
                'sequence' => '',
                'score' => 0.0,
            ],
            [
                'token' => -1,
                'token_str' => 'special-chars!@#',
                'sequence' => "Sequence with\nnewlines\tand\ttabs",
                'score' => 1.0,
            ],
            [
                'token' => 999999,
                'token_str' => '你好',
                'sequence' => 'Unicode: 你好世界',
                'score' => 0.12345,
            ],
        ];

        $result = FillMaskResult::fromArray($data);

        $this->assertCount(3, $result->fills);

        $this->assertSame(0, $result->fills[0]->token);
        $this->assertSame('', $result->fills[0]->tokenStr);
        $this->assertSame('', $result->fills[0]->sequence);
        $this->assertSame(0.0, $result->fills[0]->score);

        $this->assertSame(-1, $result->fills[1]->token);
        $this->assertSame('special-chars!@#', $result->fills[1]->tokenStr);
        $this->assertSame("Sequence with\nnewlines\tand\ttabs", $result->fills[1]->sequence);
        $this->assertSame(1.0, $result->fills[1]->score);

        $this->assertSame(999999, $result->fills[2]->token);
        $this->assertSame('你好', $result->fills[2]->tokenStr);
        $this->assertSame('Unicode: 你好世界', $result->fills[2]->sequence);
        $this->assertSame(0.12345, $result->fills[2]->score);
    }
}
