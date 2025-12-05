<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\OpenAi\Contract\Gpt;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Bridge\OpenAi\Contract\Gpt\ToolNormalizer;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

class ToolNormalizerTest extends TestCase
{
    #[DataProvider('normalizeProvider')]
    public function testNormalize(array $expected, Tool $tool)
    {
        $actual = (new ToolNormalizer())->normalize($tool, null, [Contract::CONTEXT_MODEL => new Gpt('o3')]);
        $this->assertEquals($expected, $actual);
    }

    public static function normalizeProvider(): \Generator
    {
        $tool = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description');

        $expected = [
            'type' => 'function',
            'name' => $tool->getName(),
            'description' => $tool->getDescription(),
        ];

        $parameters = [
            'type' => 'object',
            'properties' => [
                'text' => [
                    'type' => 'string',
                    'description' => 'The text given to the tool',
                ],
            ],
            'required' => ['text'],
            'additionalProperties' => false,
        ];

        yield 'no parameters' => [$expected, $tool];
        yield 'with parameters' => [
            array_merge($expected, ['parameters' => $parameters]),
            new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description', $parameters),
        ];
    }

    #[DataProvider('supportsNormalizationProvider')]
    public function testSupportsNormalization(mixed $data, Model $model, bool $expected)
    {
        $this->assertSame(
            $expected,
            (new ToolNormalizer())->supportsNormalization($data, null, [Contract::CONTEXT_MODEL => $model])
        );
    }

    public static function supportsNormalizationProvider(): \Generator
    {
        $tool = new Tool(new ExecutionReference('Foo\Bar'), 'bar', 'description');
        $gpt = new Gpt('o3');

        yield 'supported' => [$tool, $gpt, true];
        yield 'unsupported model' => [$tool, new Gemini('foo'), false];
        yield 'unsupported data' => [new Text('foo'), $gpt, false];
    }
}
