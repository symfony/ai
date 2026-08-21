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
use Symfony\AI\Mate\Discovery\DocBlockParser;
use Symfony\AI\Mate\Discovery\SchemaGenerator;
use Symfony\AI\Mate\Tests\Discovery\Fixtures\SchemaFixture;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SchemaGeneratorTest extends TestCase
{
    private SchemaGenerator $generator;

    protected function setUp(): void
    {
        $this->generator = new SchemaGenerator(new DocBlockParser());
    }

    public function testScalarsSchema()
    {
        $schema = $this->generate('scalars');

        $this->assertSame('object', $schema['type']);
        $this->assertSame(['limit'], $schema['required']);

        $properties = $schema['properties'];
        $this->assertSame('integer', $properties['limit']['type']);
        $this->assertSame('Maximum number of items', $properties['limit']['description']);
        $this->assertArrayNotHasKey('default', $properties['limit']);

        $this->assertSame(['null', 'string'], $properties['filter']['type']);
        $this->assertNull($properties['filter']['default']);

        $this->assertSame('boolean', $properties['deep']['type']);
        $this->assertFalse($properties['deep']['default']);
    }

    public function testArrayAndFloatSchema()
    {
        $schema = $this->generate('arraysAndFloats');
        $properties = $schema['properties'];

        $this->assertSame('array', $properties['tags']['type']);
        $this->assertSame(['type' => 'string'], $properties['tags']['items']);

        $this->assertSame('number', $properties['ratio']['type']);
        $this->assertSame(1.0, $properties['ratio']['default']);
    }

    public function testBackedEnumSchema()
    {
        $schema = $this->generate('withEnum');
        $color = $schema['properties']['color'];

        $this->assertSame('string', $color['type']);
        $this->assertSame(['red', 'green', 'blue'], $color['enum']);
        $this->assertSame('red', $color['default']);
    }

    public function testNoParametersProducesEmptyObject()
    {
        $schema = $this->generate('noParameters');

        $this->assertSame('object', $schema['type']);
        $this->assertInstanceOf(\stdClass::class, $schema['properties']);
        $this->assertArrayNotHasKey('required', $schema);
    }

    /**
     * @return array<string, mixed>
     */
    private function generate(string $method): array
    {
        return $this->generator->generate(new \ReflectionMethod(SchemaFixture::class, $method));
    }
}
