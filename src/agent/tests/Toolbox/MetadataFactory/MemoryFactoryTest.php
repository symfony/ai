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
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoAttribute1;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolNoAttribute2;
use Symfony\AI\Agent\Toolbox\Exception\ToolException;
use Symfony\AI\Agent\Toolbox\ToolFactory\MemoryToolFactory;
use Symfony\AI\Platform\Tool\Tool;

final class MemoryFactoryTest extends TestCase
{
    public function testGetMetadataWithoutTools()
    {
        $this->expectException(ToolException::class);
        $this->expectExceptionMessage('The reference "SomeClass" is not a valid tool.');

        $factory = new MemoryToolFactory();
        iterator_to_array($factory->getTool('SomeClass')); // @phpstan-ignore-line Yes, this class does not exist
    }

    public function testGetMetadataWithDistinctToolPerClass()
    {
        $factory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute1::class, 'happy_birthday', 'Generates birthday message')
            ->addTool(new ToolNoAttribute2(), 'checkout', 'Buys a number of items per product', 'buy');

        $metadata = iterator_to_array($factory->getTool(ToolNoAttribute1::class));

        $this->assertCount(1, $metadata);
        $this->assertInstanceOf(Tool::class, $metadata[0]);
        $this->assertSame('happy_birthday', $metadata[0]->getName());
        $this->assertSame('Generates birthday message', $metadata[0]->getDescription());
        $this->assertSame('__invoke', $metadata[0]->getReference()->getMethod());

        $expectedParams = [
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string', 'description' => 'the name of the person'],
                'years' => ['type' => 'integer', 'description' => 'the age of the person'],
            ],
            'required' => ['name', 'years'],
            'additionalProperties' => false,
        ];

        $this->assertSame($expectedParams, $metadata[0]->getParameters());
    }

    public function testGetMetadataWithMultipleToolsInClass()
    {
        $factory = (new MemoryToolFactory())
            ->addTool(ToolNoAttribute2::class, 'checkout', 'Buys a number of items per product', 'buy')
            ->addTool(ToolNoAttribute2::class, 'cancel', 'Cancels an order', 'cancel');

        $metadata = iterator_to_array($factory->getTool(ToolNoAttribute2::class));

        $this->assertCount(2, $metadata);
        $this->assertInstanceOf(Tool::class, $metadata[0]);
        $this->assertSame('checkout', $metadata[0]->getName());
        $this->assertSame('Buys a number of items per product', $metadata[0]->getDescription());
        $this->assertSame('buy', $metadata[0]->getReference()->getMethod());

        $expectedParams = [
            'type' => 'object',
            'properties' => [
                'id' => ['type' => 'integer', 'description' => 'the ID of the product'],
                'amount' => ['type' => 'integer', 'description' => 'the number of products'],
            ],
            'required' => ['id', 'amount'],
            'additionalProperties' => false,
        ];
        $this->assertSame($expectedParams, $metadata[0]->getParameters());

        $this->assertInstanceOf(Tool::class, $metadata[1]);
        $this->assertSame('cancel', $metadata[1]->getName());
        $this->assertSame('Cancels an order', $metadata[1]->getDescription());
        $this->assertSame('cancel', $metadata[1]->getReference()->getMethod());

        $expectedParams = [
            'type' => 'object',
            'properties' => [
                'orderId' => ['type' => 'string', 'description' => 'the ID of the order'],
            ],
            'required' => ['orderId'],
            'additionalProperties' => false,
        ];
        $this->assertSame($expectedParams, $metadata[1]->getParameters());
    }
}
