<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini\Contract;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Bridge\Gemini\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

#[Small]
#[CoversClass(ToolNormalizer::class)]
#[UsesClass(Model::class)]
#[UsesClass(Gemini::class)]
#[UsesClass(Tool::class)]
final class ToolNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ToolNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Tool(new ExecutionReference(ToolNoParams::class), 'test', 'test'), context: [
            Contract::CONTEXT_MODEL => new Gemini(Gemini::GEMINI_2_PRO),
        ]));
        $this->assertFalse($normalizer->supportsNormalization('not a tool'));
    }

    public function testGetSupportedTypes()
    {
        $normalizer = new ToolNormalizer();

        $expected = [
            Tool::class => true,
        ];

        $this->assertSame($expected, $normalizer->getSupportedTypes(null));
    }

    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(Tool $tool, array $expected)
    {
        $normalizer = new ToolNormalizer();

        $normalized = $normalizer->normalize($tool);

        $this->assertEquals($expected, $normalized);
    }

    /**
     * @return iterable<array{0: Tool, 1: array}>
     */
    public static function normalizeDataProvider(): iterable
    {
        yield 'call with params' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_required_params',
                'A tool with required parameters',
                [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'Text parameter',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'Number parameter',
                        ],
                        'nestedObject' => [
                            'type' => 'object',
                            'description' => 'bar',
                            'additionalProperties' => false,
                        ],
                    ],
                    'required' => ['text', 'number'],
                    'additionalProperties' => false,
                ],
            ),
            [
                'description' => 'A tool with required parameters',
                'name' => 'tool_required_params',
                'parameters' => [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'Text parameter',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'Number parameter',
                        ],
                        'nestedObject' => [
                            'type' => 'object',
                            'description' => 'bar',
                        ],
                    ],
                    'required' => ['text', 'number'],
                ],
            ],
        ];

        yield 'call without params' => [
            new Tool(
                new ExecutionReference(ToolNoParams::class, 'bar'),
                'tool_no_params',
                'A tool without parameters',
                null,
            ),
            [
                'description' => 'A tool without parameters',
                'name' => 'tool_no_params',
                'parameters' => null,
            ],
        ];
    }
}
