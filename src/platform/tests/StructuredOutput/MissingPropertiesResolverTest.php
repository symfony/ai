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
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\StructuredOutput\MissingPropertiesResolver;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\City;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Itinerary;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\MathReasoning;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\Circle;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\Drawing;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\OrderFilter;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\PolymorphicType\SearchRequest;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Step;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Ticket;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\TreeNode;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\Trip;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\User;
use Symfony\AI\Platform\Tests\Fixtures\StructuredOutput\UserWithAccessors;

final class MissingPropertiesResolverTest extends TestCase
{
    private MissingPropertiesResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new MissingPropertiesResolver();
    }

    public function testSelectsOnlyMissingProperties()
    {
        $this->assertSame(['population', 'mayor'], $this->resolver->resolve(new City(name: 'Berlin', country: 'Germany'))->toAttributes());
    }

    public function testTreatsUninitializedAndNullAsMissing()
    {
        $user = new User();
        $this->assertSame(['id', 'name', 'createdAt', 'isActive', 'age'], $this->resolver->resolve($user)->toAttributes());

        $user->id = 1;
        $user->name = 'john';
        $user->isActive = false;
        $this->assertSame(['createdAt', 'age'], $this->resolver->resolve($user)->toAttributes());
    }

    public function testReadsPrivatePropertiesBehindAccessors()
    {
        $user = new UserWithAccessors();
        $user->setId(1);
        $user->setIsActive(true);

        $this->assertSame(['name', 'createdAt', 'age'], $this->resolver->resolve($user)->toAttributes());
    }

    public function testTreatsEmptyCollectionsAsMissing()
    {
        $this->assertSame(['steps'], $this->resolver->resolve(new MathReasoning([], '', 0.0))->toAttributes());
        $this->assertSame(['stops'], $this->resolver->resolve(new Itinerary(name: 'Tour'))->toAttributes());

        $this->expectException(InvalidArgumentException::class);

        $this->resolver->resolve(new Itinerary(name: 'Tour', stops: new \ArrayObject(['Berlin'])));
    }

    public function testSelectsMissingPropertiesOfNestedObjects()
    {
        $selection = $this->resolver->resolve(new Trip(destination: new City(name: 'Berlin', country: 'Germany')));

        $this->assertSame(['title', 'destination' => ['population', 'mayor'], 'origin'], $selection->toAttributes());
    }

    public function testLeavesOutNestedObjectsWithNothingMissing()
    {
        $trip = new Trip(destination: new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner'));

        $this->assertSame(['title', 'origin'], $this->resolver->resolve($trip)->toAttributes());
    }

    public function testLeavesOutPropertiesThatCannotBeWrittenOntoTheInstance()
    {
        // The readonly code can only be set through the constructor, which populating an existing instance never calls
        $this->assertSame(['seat'], $this->resolver->resolve(new Ticket())->toAttributes());
    }

    public function testCarriesTheDiscriminatorOfPolymorphicNestedObjects()
    {
        $selection = $this->resolver->resolve(new SearchRequest(query: 'cheap flights', filter: new OrderFilter(number: '42')));

        $this->assertSame(['filter' => ['userResponsible', 'departureDate']], $selection->toAttributes());

        $filter = $selection->getProperties()['filter'];
        $this->assertNotNull($filter);
        $this->assertSame('type', $filter->getDiscriminatorProperty());
        $this->assertSame('order', $filter->getDiscriminatorValue());
    }

    public function testTakesTheDiscriminatorFromTheSerializerMetadata()
    {
        // "kind" only exists in the DiscriminatorMap, the Circle instance has no such property to read it from
        $selection = $this->resolver->resolve(new Drawing(title: 'Logo', shape: new Circle(radius: 2.0)));

        $this->assertSame(['shape' => ['color']], $selection->toAttributes());

        $shape = $selection->getProperties()['shape'];
        $this->assertNotNull($shape);
        $this->assertSame('kind', $shape->getDiscriminatorProperty());
        $this->assertSame('circle', $shape->getDiscriminatorValue());
    }

    public function testSelectsMissingPropertiesOfSelfReferencingObjects()
    {
        // The root is complete but for its child, and the child still lacks its own child
        $tree = new TreeNode(label: 'root', child: new TreeNode(label: 'leaf'));

        $this->assertSame(['child' => ['child']], $this->resolver->resolve($tree)->toAttributes());
    }

    public function testStopsAtObjectsAlreadyBeingResolved()
    {
        // The child points back to the root, which must not be resolved a second time
        $root = new TreeNode(label: 'root');
        $root->child = new TreeNode(child: $root);

        $this->assertSame(['child' => ['label']], $this->resolver->resolve($root)->toAttributes());
    }

    public function testThrowsWhenNothingIsMissing()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The given "%s" instance has no missing properties left to describe.', City::class));

        $this->resolver->resolve(new City(name: 'Berlin', population: 3500000, country: 'Germany', mayor: 'Kai Wegner'));
    }

    public function testThrowsWhenOnlyScalarsAndFilledCollectionsAreGiven()
    {
        $this->expectException(InvalidArgumentException::class);

        // A non-empty collection, an empty string and a zero are all given: nothing is left to select
        $this->resolver->resolve(new MathReasoning([new Step('a', 'b')], '', 0.0));
    }
}
