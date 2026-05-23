<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Normalizer;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Normalizer\JsonFenceStripNormalizer;
use Symfony\AI\Platform\Result\ObjectResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\VectorResult;
use Symfony\AI\Platform\Vector\Vector;

final class JsonFenceStripNormalizerTest extends TestCase
{
    public function testStripsJsonFencedBlock()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $input = "```json\n{\"foo\": \"bar\"}\n```";

        $this->assertSame('{"foo": "bar"}', $normalizer->normalize($input));
    }

    public function testStripsFencedBlockWithoutLanguageTag()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $input = "```\n{\"foo\": \"bar\"}\n```";

        $this->assertSame('{"foo": "bar"}', $normalizer->normalize($input));
    }

    public function testLeavesTextUnchangedWhenFenceContentsAreInvalidJson()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $input = "```json\nnot valid json\n```";

        $this->assertSame($input, $normalizer->normalize($input));
    }

    public function testLeavesTextUnchangedWhenNoFencePresent()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $input = '{"foo": "bar"}';

        $this->assertSame($input, $normalizer->normalize($input));
    }

    public function testReturnsEmptyStringOnEmptyInput()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertSame('', $normalizer->normalize(''));
    }

    public function testSupportsReturnsTrueForJsonResponseFormat()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertTrue($normalizer->supports(
            new Model('gpt-4'),
            new TextResult('foo'),
            ['response_format' => 'json'],
        ));
    }

    public function testSupportsReturnsTrueForJsonSchemaResponseFormat()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertTrue($normalizer->supports(
            new Model('gpt-4'),
            new TextResult('foo'),
            ['response_format' => ['type' => 'json_schema', 'json_schema' => []]],
        ));
    }

    public function testSupportsReturnsTrueForObjectResultRegardlessOfOptions()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertTrue($normalizer->supports(
            new Model('gpt-4'),
            new ObjectResult(['foo' => 'bar']),
            [],
        ));
    }

    public function testSupportsReturnsFalseWithoutResponseFormatAndTextResult()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertFalse($normalizer->supports(
            new Model('gpt-4'),
            new TextResult('foo'),
            [],
        ));
    }

    public function testSupportsReturnsFalseForVectorResult()
    {
        $normalizer = new JsonFenceStripNormalizer();

        $this->assertFalse($normalizer->supports(
            new Model('text-embedding-3-small'),
            new VectorResult([new Vector([0.1, 0.2])]),
            [],
        ));
    }
}
