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
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ColorProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ContextAwareProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\SearchQueryDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;
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

    public function testMergesFragmentFromProviderOnParameter()
    {
        $describer = new SchemaAttributeDescriber([
            StatusProvider::class => new StatusProvider(['active', 'archived']),
        ]);

        $subject = new PropertySubject('status', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status'));
        $schema = ['type' => 'string', 'description' => 'pre-existing'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame([
            'type' => 'string',
            'description' => 'pre-existing',
            'enum' => ['active', 'archived'],
        ], $schema);
    }

    public function testMergesFragmentFromProviderOnProperty()
    {
        $describer = new SchemaAttributeDescriber([
            ColorProvider::class => new ColorProvider(['red', 'blue']),
        ]);

        $subject = new PropertySubject('color', new \ReflectionProperty(SearchQueryDto::class, 'color'));
        $schema = ['type' => 'string'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['red', 'blue']], $schema);
    }

    public function testMergesFragmentWithContext()
    {
        $describer = new SchemaAttributeDescriber([
            ContextAwareProvider::class => new ContextAwareProvider(),
        ]);

        $subject = new PropertySubject('category', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'category'));
        $schema = ['type' => 'string'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['foo', 'bar']], $schema);
    }

    public function testThrowsWhenProviderIsNotRegistered()
    {
        $describer = new SchemaAttributeDescriber();

        $subject = new PropertySubject('status', new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status'));
        $schema = ['type' => 'string'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema provider "'.StatusProvider::class.'" is not registered.');

        $describer->describeProperty($subject, $schema);
    }

    public function testResolvesProviderByArbitraryServiceId()
    {
        $describer = new SchemaAttributeDescriber([
            'app.provider.tag' => new StatusProvider(['draft', 'published']),
        ]);

        $subject = new PropertySubject('tag', new \ReflectionParameter([SearchQueryDto::class, 'searchByServiceId'], 'tag'));
        $schema = ['type' => 'string'];

        $describer->describeProperty($subject, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['draft', 'published']], $schema);
    }
}
