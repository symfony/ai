<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Enricher;

use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Tests\Fixtures\Tool\ToolWithObjectAccessors;
use Symfony\AI\Platform\Contract\JsonSchema\Enricher\SchemaAttributeEnricher;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\IOException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ColorProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\ContextAwareProvider;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\LongerStaticEnumDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\SearchQueryDto;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\ExampleDto;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\SchemaAttributeRefDto;

final class SchemaAttributeEnricherTest extends TestCase
{
    /**
     * @param array<string, mixed> $expectedSchema
     */
    #[TestWith([['enum' => [7, 19]], new \ReflectionParameter([ExampleDto::class, '__construct'], 'taxRate')], 'parameter')]
    #[TestWith([['const' => 42], new \ReflectionParameter([ToolWithObjectAccessors::class, 'setValue2'], 0)], 'setter')]
    #[TestWith([['pattern' => '^foo$'], new \ReflectionParameter([ToolWithObjectAccessors::class, '__construct'], 'value3')], 'constructor')]
    #[TestWith([['description' => 'The quantity of the ingredient', 'example' => '2 cups'], new \ReflectionParameter([ExampleDto::class, '__construct'], 'quantity')], 'example')]
    #[TestWith([['type' => 'string', 'description' => 'This is a test schema from a ref file.'], new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'schemaFromFile')], 'schema from file')]
    public function testApply(array $expectedSchema, \ReflectionParameter|\ReflectionProperty $property)
    {
        $enricher = new SchemaAttributeEnricher();
        $schema = [];

        $schema = $enricher->apply($property, $schema);

        $this->assertSame($expectedSchema, $schema);
    }

    public function testApplyWithNonExistentFile()
    {
        $enricher = new SchemaAttributeEnricher();
        $property = new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'nonExistentSchema');
        $schema = [];

        $this->expectException(InvalidArgumentException::class);
        $schema = $enricher->apply($property, $schema);
    }

    public function testApplyWithInvalidJson()
    {
        $enricher = new SchemaAttributeEnricher();
        $property = new \ReflectionParameter([SchemaAttributeRefDto::class, '__construct'], 'nonJsonSchema');
        $schema = [];

        $this->expectException(IOException::class);
        $schema = $enricher->apply($property, $schema);
    }

    public function testMergesFragmentFromProviderOnParameter()
    {
        $enricher = new SchemaAttributeEnricher([
            StatusProvider::class => new StatusProvider(['active', 'archived']),
        ]);

        $reflector = new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status');
        $schema = ['type' => 'string', 'description' => 'pre-existing'];

        $schema = $enricher->apply($reflector, $schema);

        $this->assertSame([
            'type' => 'string',
            'description' => 'pre-existing',
            'enum' => ['active', 'archived'],
        ], $schema);
    }

    public function testMergesFragmentFromProviderOnProperty()
    {
        $enricher = new SchemaAttributeEnricher([
            ColorProvider::class => new ColorProvider(['red', 'blue']),
        ]);

        $reflector = new \ReflectionProperty(SearchQueryDto::class, 'color');
        $schema = ['type' => 'string'];

        $schema = $enricher->apply($reflector, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['red', 'blue']], $schema);
    }

    public function testMergesFragmentWithContext()
    {
        $enricher = new SchemaAttributeEnricher([
            ContextAwareProvider::class => new ContextAwareProvider(),
        ]);

        $reflector = new \ReflectionParameter([SearchQueryDto::class, 'search'], 'category');
        $schema = ['type' => 'string'];

        $schema = $enricher->apply($reflector, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['foo', 'bar']], $schema);
    }

    public function testThrowsWhenProviderIsNotRegistered()
    {
        $enricher = new SchemaAttributeEnricher();

        $reflector = new \ReflectionParameter([SearchQueryDto::class, 'search'], 'status');
        $schema = ['type' => 'string'];

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Schema provider "'.StatusProvider::class.'" is not registered.');

        $schema = $enricher->apply($reflector, $schema);
    }

    public function testResolvesProviderByArbitraryServiceId()
    {
        $enricher = new SchemaAttributeEnricher([
            'app.provider.tag' => new StatusProvider(['draft', 'published']),
        ]);

        $reflector = new \ReflectionParameter([SearchQueryDto::class, 'searchByServiceId'], 'tag');
        $schema = ['type' => 'string'];

        $schema = $enricher->apply($reflector, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['draft', 'published']], $schema);
    }

    public function testProviderEnumFullyReplacesLongerStaticEnum()
    {
        $enricher = new SchemaAttributeEnricher([
            StatusProvider::class => new StatusProvider(['active', 'archived']),
        ]);

        // LongerStaticEnumDto::$status carries #[Schema(enum: ['a', 'b', 'c'], provider: StatusProvider::class)].
        $reflector = new \ReflectionProperty(LongerStaticEnumDto::class, 'status');
        $schema = ['type' => 'string'];

        $schema = $enricher->apply($reflector, $schema);

        // The runtime fragment must replace the static enum wholesale, not merge by index
        // (which would leave the trailing 'c' from the static enum behind).
        $this->assertSame(['type' => 'string', 'enum' => ['active', 'archived']], $schema);
    }

    public function testProviderEnumReplacesEnumLeftByEarlierEnricher()
    {
        $enricher = new SchemaAttributeEnricher([
            StatusProvider::class => new StatusProvider(['active', 'archived']),
        ]);

        // SearchQueryDto::$status only carries #[Schema(provider: StatusProvider::class)] (no
        // static enum), so the incoming enum stands in for one an earlier enricher produced
        // (e.g. the generator listing cases() for a backed-enum-typed property).
        $reflector = new \ReflectionProperty(SearchQueryDto::class, 'status');
        $schema = ['type' => 'string', 'enum' => ['draft', 'review', 'published', 'archived']];

        $schema = $enricher->apply($reflector, $schema);

        $this->assertSame(['type' => 'string', 'enum' => ['active', 'archived']], $schema);
    }
}
