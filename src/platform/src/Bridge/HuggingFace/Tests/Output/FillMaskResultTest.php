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

        $this->assertSame($fills, $result->getFills());
        $this->assertCount(3, $result->getFills());
    }

    #[TestDox('Construction with empty array creates valid instance')]
    public function testConstructionWithEmptyArray()
    {
        $result = new FillMaskResult([]);

        $this->assertSame([], $result->getFills());
        $this->assertCount(0, $result->getFills());
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

        $this->assertCount(3, $result->getFills());

        $this->assertSame(1234, $result->getFills()[0]->getToken());
        $this->assertSame('happy', $result->getFills()[0]->getTokenStr());
        $this->assertSame('I feel happy today', $result->getFills()[0]->getSequence());
        $this->assertSame(0.95, $result->getFills()[0]->getScore());

        $this->assertSame(5678, $result->getFills()[1]->getToken());
        $this->assertSame('great', $result->getFills()[1]->getTokenStr());
        $this->assertSame('I feel great today', $result->getFills()[1]->getSequence());
        $this->assertSame(0.03, $result->getFills()[1]->getScore());

        $this->assertSame(9012, $result->getFills()[2]->getToken());
        $this->assertSame('wonderful', $result->getFills()[2]->getTokenStr());
        $this->assertSame('I feel wonderful today', $result->getFills()[2]->getSequence());
        $this->assertSame(0.02, $result->getFills()[2]->getScore());
    }

    #[TestDox('fromArray with empty data creates empty result')]
    public function testFromArrayWithEmptyData()
    {
        $result = FillMaskResult::fromArray([]);

        $this->assertCount(0, $result->getFills());
        $this->assertSame([], $result->getFills());
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

        $this->assertCount(1, $result->getFills());
        $this->assertInstanceOf(MaskFill::class, $result->getFills()[0]);
        $this->assertSame(999, $result->getFills()[0]->getToken());
        $this->assertSame('word', $result->getFills()[0]->getTokenStr());
        $this->assertSame('The word is here', $result->getFills()[0]->getSequence());
        $this->assertSame(0.99, $result->getFills()[0]->getScore());
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

        $this->assertSame('first', $result->getFills()[0]->getTokenStr());
        $this->assertSame('second', $result->getFills()[1]->getTokenStr());
        $this->assertSame('third', $result->getFills()[2]->getTokenStr());
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

        $this->assertCount(3, $result->getFills());

        $this->assertSame(0, $result->getFills()[0]->getToken());
        $this->assertSame('', $result->getFills()[0]->getTokenStr());
        $this->assertSame('', $result->getFills()[0]->getSequence());
        $this->assertSame(0.0, $result->getFills()[0]->getScore());

        $this->assertSame(-1, $result->getFills()[1]->getToken());
        $this->assertSame('special-chars!@#', $result->getFills()[1]->getTokenStr());
        $this->assertSame("Sequence with\nnewlines\tand\ttabs", $result->getFills()[1]->getSequence());
        $this->assertSame(1.0, $result->getFills()[1]->getScore());

        $this->assertSame(999999, $result->getFills()[2]->getToken());
        $this->assertSame('你好', $result->getFills()[2]->getTokenStr());
        $this->assertSame('Unicode: 你好世界', $result->getFills()[2]->getSequence());
        $this->assertSame(0.12345, $result->getFills()[2]->getScore());
    }
}
