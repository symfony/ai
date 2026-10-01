<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\Contract;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Bridge\Gemini\Contract\ToolNormalizer;
use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

/**
 * @phpstan-import-type JsonSchema from Factory
 */
final class ToolNormalizerTest extends TestCase
{
    public function testSupportsNormalization()
    {
        $normalizer = new ToolNormalizer();

        $this->assertTrue($normalizer->supportsNormalization(new Tool(new ExecutionReference(ToolNoParams::class), 'test', 'test'), context: [
            Contract::CONTEXT_MODEL => new Gemini('gemini-2.0-flash'),
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

    /**
     * @param array{name: string, description: string, parametersJsonSchema?: JsonSchema|array{type: 'object'}} $expected
     */
    #[DataProvider('normalizeDataProvider')]
    public function testNormalize(Tool $tool, array $expected)
    {
        $normalizer = new ToolNormalizer();

        $normalized = $normalizer->normalize($tool);

        $this->assertSame($expected, $normalized);
    }

    /**
     * @return iterable<array{0: Tool, 1: array}>
     */
    public static function normalizeDataProvider(): iterable
    {
        $schema = [
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
        ];

        yield 'call with params' => [
            new Tool(new ExecutionReference(ToolRequiredParams::class, 'bar'), 'tool_required_params', 'A tool with required parameters', $schema),
            [
                'description' => 'A tool with required parameters',
                'name' => 'tool_required_params',
                'parametersJsonSchema' => $schema,
            ],
        ];

        yield 'call without params' => [
            new Tool(new ExecutionReference(ToolNoParams::class, 'bar'), 'tool_no_params', 'A tool without parameters', null),
            [
                'description' => 'A tool without parameters',
                'name' => 'tool_no_params',
            ],
        ];

        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'properties' => [
                'name' => [
                    'type' => ['string', 'null'],
                    'description' => 'A nullable name',
                ],
                'fields' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'value' => [
                                'type' => ['string', 'number', 'boolean'],
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'call with JSON Schema keywords unknown to the OpenAPI subset (e.g. produced by an MCP server)' => [
            // @phpstan-ignore argument.type (testing type unions and meta-keys outside of the generated JsonSchema shape)
            new Tool(new ExecutionReference(ToolRequiredParams::class, 'bar'), 'tool_json_schema', 'A tool with a raw JSON Schema', $schema),
            [
                'description' => 'A tool with a raw JSON Schema',
                'name' => 'tool_json_schema',
                'parametersJsonSchema' => $schema,
            ],
        ];
    }
}
