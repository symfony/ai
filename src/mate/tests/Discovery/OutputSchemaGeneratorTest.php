<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Discovery;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Discovery\OutputSchemaGenerator;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\OutputSchema\ImportingTool;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\OutputSchema\SimpleTool;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class OutputSchemaGeneratorTest extends TestCase
{
    private OutputSchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new OutputSchemaGenerator();
    }

    public function testSimpleObjectShape()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'simpleShape');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'age' => ['type' => 'integer'],
            ],
            'required' => ['name', 'age'],
        ], $schema);
    }

    public function testNestedArrayShape()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'nestedShape');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'entries' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'title' => ['type' => 'string'],
                        ],
                        'required' => ['id', 'title'],
                    ],
                ],
            ],
            'required' => ['entries'],
        ], $schema);
    }

    public function testOptionalKeys()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'optionalKeys');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'status' => ['type' => 'string'],
                'message' => ['type' => 'string'],
            ],
            'required' => ['status'],
        ], $schema);
    }

    public function testNullableValues()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'nullableValues');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'name' => ['type' => 'string'],
                'parent' => ['type' => ['string', 'null']],
            ],
            'required' => ['name', 'parent'],
        ], $schema);
    }

    public function testMapType()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'mapType');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'additionalProperties' => [
                'type' => ['string', 'null'],
            ],
        ], $schema);
    }

    public function testStringArrayType()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'stringArray');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'channels' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                ],
            ],
            'required' => ['channels'],
        ], $schema);
    }

    public function testNoReturnType()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'noReturnType');
        $schema = $this->generator->generate($method);

        $this->assertNull($schema);
    }

    public function testVoidReturnType()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'voidReturn');
        $schema = $this->generator->generate($method);

        $this->assertNull($schema);
    }

    public function testPhpstanReturnTakesPrecedence()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'phpstanReturn');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'count' => ['type' => 'integer'],
            ],
            'required' => ['count'],
        ], $schema);
    }

    public function testImportedType()
    {
        $method = new \ReflectionMethod(ImportingTool::class, 'withImportedType');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'active' => ['type' => 'boolean'],
                        ],
                        'required' => ['id', 'name', 'active'],
                    ],
                ],
            ],
            'required' => ['items'],
        ], $schema);
    }

    public function testListWithNestedObjectShape()
    {
        $method = new \ReflectionMethod(SimpleTool::class, 'listWithFiles');
        $schema = $this->generator->generate($method);

        $this->assertSame([
            'type' => 'object',
            'properties' => [
                'files' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'name' => ['type' => 'string'],
                            'size' => ['type' => 'integer'],
                        ],
                        'required' => ['name', 'size'],
                    ],
                ],
            ],
            'required' => ['files'],
        ], $schema);
    }
}
