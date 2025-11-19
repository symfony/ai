<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\Normalizer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Fixtures\Tool\ToolException;
use Symfony\AI\Fixtures\Tool\ToolNoParams;
use Symfony\AI\Fixtures\Tool\ToolOptionalParam;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Platform\Contract\Normalizer\ToolNormalizer;
use Symfony\AI\Platform\Tool\ExecutionReference;
use Symfony\AI\Platform\Tool\Tool;

class ToolNormalizerTest extends TestCase
{
    #[DataProvider('provideTools')]
    public function testNormalize(Tool $tool, array $expected)
    {
        $this->assertSame($expected, (new ToolNormalizer())->normalize($tool));
    }

    public static function provideTools(): \Generator
    {
        yield 'required params' => [
            new Tool(
                new ExecutionReference(ToolRequiredParams::class, 'bar'),
                'tool_required_params',
                'A tool with required parameters',
                [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'The text given to the tool',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'A number given to the tool',
                        ],
                    ],
                    'required' => ['text', 'number'],
                    'additionalProperties' => false,
                ],
            ),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tool_required_params',
                    'description' => 'A tool with required parameters',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'The text given to the tool',
                            ],
                            'number' => [
                                'type' => 'integer',
                                'description' => 'A number given to the tool',
                            ],
                        ],
                        'required' => ['text', 'number'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        yield 'optional param' => [
            new Tool(
                new ExecutionReference(ToolOptionalParam::class, 'bar'),
                'tool_optional_param',
                'A tool with one optional parameter',
                [
                    'type' => 'object',
                    'properties' => [
                        'text' => [
                            'type' => 'string',
                            'description' => 'The text given to the tool',
                        ],
                        'number' => [
                            'type' => 'integer',
                            'description' => 'A number given to the tool',
                        ],
                    ],
                    'required' => ['text'],
                    'additionalProperties' => false,
                ],
            ),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tool_optional_param',
                    'description' => 'A tool with one optional parameter',
                    'parameters' => [
                        'type' => 'object',
                        'properties' => [
                            'text' => [
                                'type' => 'string',
                                'description' => 'The text given to the tool',
                            ],
                            'number' => [
                                'type' => 'integer',
                                'description' => 'A number given to the tool',
                            ],
                        ],
                        'required' => ['text'],
                        'additionalProperties' => false,
                    ],
                ],
            ],
        ];

        yield 'no params' => [
            new Tool(
                new ExecutionReference(ToolNoParams::class),
                'tool_no_params',
                'A tool without parameters',
            ),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tool_no_params',
                    'description' => 'A tool without parameters',
                ],
            ],
        ];

        yield 'exception' => [
            new Tool(
                new ExecutionReference(ToolException::class, 'bar'),
                'tool_exception',
                'This tool is broken',
            ),
            [
                'type' => 'function',
                'function' => [
                    'name' => 'tool_exception',
                    'description' => 'This tool is broken',
                ],
            ],
        ];
    }
}
