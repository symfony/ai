<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\StructuredOutput;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Factory;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\StructuredOutput\PropertySelection;
use Symfony\AI\Platform\StructuredOutput\SchemaSelector;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\Drawing;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\SearchRequest;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Trip;

final class SchemaSelectorTest extends TestCase
{
    private SchemaSelector $selector;

    protected function setUp(): void
    {
        $this->selector = new SchemaSelector();
    }

    public function testKeepsOnlySelectedProperties()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'population' => ['type' => ['integer', 'null']],
                'mayor' => ['type' => ['string', 'null']],
            ],
        ];

        $this->assertSame($expected, $this->selector->select($this->schemaOf(City::class), new PropertySelection(['population' => null, 'mayor' => null])));
    }

    public function testKeepsTheFullItemSchemaOfSelectedCollections()
    {
        $schema = $this->selector->select($this->schemaOf(MathReasoning::class), new PropertySelection(['steps' => null]));

        $this->assertSame(['steps'], array_keys($schema['properties']));
        $this->assertSame(['steps'], $schema['required']);
        $this->assertSame(['explanation', 'output'], array_keys($schema['properties']['steps']['items']['properties']));
    }

    public function testNarrowsNestedObjects()
    {
        $expected = [
            'type' => 'object',
            'properties' => [
                'title' => ['type' => ['string', 'null']],
                // Narrowed to its selection, and no longer nullable as it already exists
                'destination' => [
                    'type' => 'object',
                    'properties' => [
                        'population' => ['type' => ['integer', 'null']],
                        'mayor' => ['type' => ['string', 'null']],
                    ],
                ],
                // Selected without nested selection: the full class
                'origin' => [
                    'type' => ['object', 'null'],
                    'properties' => [
                        'name' => ['type' => ['string', 'null']],
                        'population' => ['type' => ['integer', 'null']],
                        'country' => ['type' => ['string', 'null']],
                        'mayor' => ['type' => ['string', 'null']],
                    ],
                ],
            ],
        ];

        $selection = new PropertySelection([
            'title' => null,
            'destination' => new PropertySelection(['population' => null, 'mayor' => null]),
            'origin' => null,
        ]);

        $this->assertSame($expected, $this->selector->select($this->schemaOf(Trip::class), $selection));
    }

    public function testNarrowsTheBranchMatchingTheDiscriminator()
    {
        $selection = new PropertySelection([
            'filter' => new PropertySelection(['userResponsible' => null, 'departureDate' => null], 'type', 'order'),
        ]);

        $schema = $this->selector->select($this->schemaOf(SearchRequest::class), $selection);

        $this->assertSame(['filter'], array_keys($schema['properties']));
        $this->assertCount(1, $schema['properties']['filter']['anyOf']);
        $this->assertSame(['userResponsible', 'departureDate'], array_keys($schema['properties']['filter']['anyOf'][0]['properties']));
    }

    public function testNarrowsTheBranchMatchingADiscriminatorOutsideTheClass()
    {
        $selection = new PropertySelection(['shape' => new PropertySelection(['color' => null], 'kind', 'circle')]);

        $schema = $this->selector->select($this->schemaOf(Drawing::class), $selection);

        $this->assertSame(['shape'], array_keys($schema['properties']));
        $this->assertCount(1, $schema['properties']['shape']['anyOf']);
        $this->assertSame(['color'], array_keys($schema['properties']['shape']['anyOf'][0]['properties']));
    }

    public function testNarrowsTheBranchOfAOneOf()
    {
        $schema = $this->schemaOf(SearchRequest::class);
        $schema['properties']['filter'] = ['oneOf' => $schema['properties']['filter']['anyOf']];
        $selection = new PropertySelection([
            'filter' => new PropertySelection(['userResponsible' => null, 'departureDate' => null], 'type', 'order'),
        ]);

        $schema = $this->selector->select($schema, $selection);

        $this->assertCount(1, $schema['properties']['filter']['oneOf']);
        $this->assertSame(['userResponsible', 'departureDate'], array_keys($schema['properties']['filter']['oneOf'][0]['properties']));
    }

    public function testKeepsNestedObjectsItCannotNarrowInFull()
    {
        // Without discriminator, two object branches are ambiguous
        $schema = $this->schemaOf(SearchRequest::class);
        $selection = new PropertySelection(['filter' => new PropertySelection(['userResponsible' => null])]);

        $this->assertSame($schema['properties']['filter'], $this->selector->select($schema, $selection)['properties']['filter']);
    }

    public function testNarrowsNestedObjectsBehindAReference()
    {
        $schema = $this->selector->select($this->treeNodeSchema(), new PropertySelection([
            'child' => new PropertySelection(['child' => null]),
        ]));

        $this->assertSame(['child'], array_keys($schema['properties']));
        $this->assertSame(['child'], array_keys($schema['properties']['child']['properties']));
        $this->assertSame(['$ref' => '#/$defs/TreeNode'], $schema['properties']['child']['properties']['child']);
    }

    public function testNarrowsEveryOccurrenceOfAReferenceOnItsOwn()
    {
        $schema = $this->selector->select($this->treeNodeSchema(), new PropertySelection([
            'child' => new PropertySelection(['label' => null, 'child' => new PropertySelection(['label' => null])]),
        ]));

        $child = $schema['properties']['child'];
        $this->assertSame(['label', 'child'], array_keys($child['properties']));
        $this->assertSame(['label'], array_keys($child['properties']['child']['properties']));
    }

    public function testTellsTheSelectionASchemaAsksFor()
    {
        $this->assertSame(['name', 'population', 'country', 'mayor'], $this->selector->selectionOf($this->schemaOf(City::class))->toAttributes());

        $city = ['name', 'population', 'country', 'mayor'];
        $this->assertSame(['title', 'destination' => $city, 'origin' => $city], $this->selector->selectionOf($this->schemaOf(Trip::class))->toAttributes());
    }

    public function testTellsTheSelectionOfCollectionItems()
    {
        $this->assertSame(
            ['steps' => ['explanation', 'output'], 'finalAnswer', 'result'],
            $this->selector->selectionOf($this->schemaOf(MathReasoning::class))->toAttributes(),
        );
    }

    public function testMergesTheSelectionsOfAllBranches()
    {
        $this->assertSame(
            ['query', 'filter' => ['type', 'number', 'userResponsible', 'departureDate', 'contractNumber', 'subsidiary']],
            $this->selector->selectionOf($this->schemaOf(SearchRequest::class))->toAttributes(),
        );
    }

    public function testSelectsRecursiveReferencesInFull()
    {
        $this->assertSame(['label', 'child' => ['label', 'child']], $this->selector->selectionOf($this->treeNodeSchema())->toAttributes());
    }

    public function testThrowsWhenNoSelectedPropertyIsPartOfTheSchema()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('None of the selected properties is part of the schema.');

        $this->selector->select($this->schemaOf(City::class), new PropertySelection(['unknown' => null]));
    }

    public function testKeepsSchemaWithoutPropertiesAsIs()
    {
        $this->assertSame(['type' => 'object'], $this->selector->select(['type' => 'object'], new PropertySelection(['name' => null])));
    }

    /**
     * @param class-string $class
     *
     * @return array<string, mixed>
     */
    private function schemaOf(string $class): array
    {
        $schema = (new Factory())->buildProperties($class);
        $this->assertNotNull($schema);

        return $schema;
    }

    /**
     * The shape a self-referential class takes once the schema factory supports recursion through `$defs`:
     * the nested object is a `$ref`, so its `properties` are not inline.
     *
     * @return array<string, mixed>
     */
    private function treeNodeSchema(): array
    {
        $node = [
            'type' => 'object',
            'properties' => [
                'label' => ['type' => ['string', 'null']],
                'child' => ['$ref' => '#/$defs/TreeNode'],
            ],
            'required' => ['label', 'child'],
            'additionalProperties' => false,
        ];

        return $node + ['$defs' => ['TreeNode' => $node]];
    }
}
