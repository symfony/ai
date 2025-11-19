<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\MetadataFactory;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactory\ReflectionToolFactory;
use Symfony\AI\Fixtures\Tool\ToolMultiple;
use Symfony\AI\Fixtures\Tool\ToolRequiredParams;
use Symfony\AI\Fixtures\Tool\ToolWrong;
use Symfony\AI\Platform\Tool\Tool;

final class ReflectionFactoryTest extends TestCase
{
    private ReflectionToolFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new ReflectionToolFactory();
    }

    public function testInvalidReferenceNonExistingClass()
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('The reference "invalid" is not a valid tool.');

        iterator_to_array($this->factory->getTool('invalid')); // @phpstan-ignore-line Yes, this class does not exist
    }

    public function testWithoutAttribute()
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage(\sprintf('The class "%s" is not a tool, please add %s attribute.', ToolWrong::class, AsTool::class));

        iterator_to_array($this->factory->getTool(ToolWrong::class));
    }

    public function testGetDefinition()
    {
        /** @var Tool[] $metadatas */
        $metadatas = iterator_to_array($this->factory->getTool(ToolRequiredParams::class));

        $this->assertToolConfiguration(
            metadata: $metadatas[0],
            className: ToolRequiredParams::class,
            name: 'tool_required_params',
            description: 'A tool with required parameters',
            method: 'bar',
            parameters: [
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
        );
    }

    public function testGetDefinitionWithMultiple()
    {
        $metadatas = iterator_to_array($this->factory->getTool(ToolMultiple::class));

        $this->assertCount(2, $metadatas);

        [$first, $second] = $metadatas;

        $this->assertToolConfiguration(
            metadata: $first,
            className: ToolMultiple::class,
            name: 'tool_hello_world',
            description: 'Function to say hello',
            method: 'hello',
            parameters: [
                'type' => 'object',
                'properties' => [
                    'world' => [
                        'type' => 'string',
                        'description' => 'The world to say hello to',
                    ],
                ],
                'required' => ['world'],
                'additionalProperties' => false,
            ],
        );

        $this->assertToolConfiguration(
            metadata: $second,
            className: ToolMultiple::class,
            name: 'tool_required_params',
            description: 'Function to say a number',
            method: 'bar',
            parameters: [
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
        );
    }

    private function assertToolConfiguration(Tool $metadata, string $className, string $name, string $description, string $method, array $parameters): void
    {
        $this->assertSame($className, $metadata->getReference()->getClass());
        $this->assertSame($method, $metadata->getReference()->getMethod());
        $this->assertSame($name, $metadata->getName());
        $this->assertSame($description, $metadata->getDescription());
        $this->assertSame($parameters, $metadata->getParameters());
    }
}
