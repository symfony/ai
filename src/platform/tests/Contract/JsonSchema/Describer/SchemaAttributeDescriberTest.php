<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Describer;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithObjectAccessors;
use Symfony\AI\Platform\Contract\JsonSchema\Describer\SchemaAttributeDescriber;
use Symfony\AI\Platform\Contract\JsonSchema\Subject\PropertySubject;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\IOException;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SchemaAttributeRefDto;

final class SchemaAttributeDescriberTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedSchema
     */
    #[TestWith([['enum' => [7, 19]], new PropertySubject('taxRate', new \ReflectionParameter([ExampleDto::class, '__construct'], 'taxRate'))], 'parameter')]
    #[TestWith([['const' => 42], new PropertySubject('value2', new \ReflectionParameter([ToolWithObjectAccessors::class, 'setValue2'], 0))], 'setter')]
    #[TestWith([['pattern' => '^foo$'], new PropertySubject('value3', new \ReflectionParameter([ToolWithObjectAccessors::class, '__construct'], 'value3'))], 'constructor')]
    #[TestWith([['description' => 'The quantity of the ingredient', 'example' => '2 cups'], new PropertySubject('quantity', new \ReflectionParameter([ExampleDto::class, '__construct'], 'quantity'))], 'example')]
    #[TestWith([['type' => 'string', 'description' => 'This is a test schema from a ref file.'], new PropertySubject('schemaFromFile', new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'schemaFromFile'))], 'schema from file')]
    public function testDescribeProperty(array $expectedSchema, PropertySubject $property)
    {
        $describer = new SchemaAttributeDescriber();
        $schema = null;

        $describer->describeProperty($property, $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    public function testDescribePropertyWithNonExistentFile()
    {
        $describer = new SchemaAttributeDescriber();
        $property = new PropertySubject('nonExistentSchema', new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'nonExistentSchema'));
        $schema = null;

        $this->expectException(InvalidArgumentException::class);
        $describer->describeProperty($property, $schema);
    }

    public function testDescribePropertyWithInvalidJson()
    {
        $describer = new SchemaAttributeDescriber();
        $property = new PropertySubject('nonJsonSchema', new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'nonJsonSchema'));
        $schema = null;

        $this->expectException(IOException::class);
        $describer->describeProperty($property, $schema);
    }
}
