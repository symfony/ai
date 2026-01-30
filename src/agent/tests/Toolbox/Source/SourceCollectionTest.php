<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Source;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Source\Source;
use Symfony\AI\Agent\Toolbox\Source\SourceCollection;

final class SourceCollectionTest extends TestCase
{
    public function testAllReturnsEmptyArrayWhenNoSourcesAdded()
    {
        $collection = new SourceCollection();

        $this->assertSame([], $collection->all());
    }

    public function testAllReturnsArrayWhenOnInitialSources()
    {
        $collection = new SourceCollection([
            new Source('name1', 'reference1', 'content1'),
            new Source('name2', 'reference2', 'content2'),
        ]);

        $this->assertCount(2, $collection->all());
    }

    public function testAddSingleSource()
    {
        $collection = new SourceCollection();
        $source = new Source('name', 'reference', 'content');

        $collection->add($source);

        $this->assertCount(1, $collection->all());
        $this->assertSame($source, $collection->all()[0]);
    }

    public function testAddMultipleSources()
    {
        $collection = new SourceCollection();
        $source1 = new Source('name1', 'reference1', 'content1');
        $source2 = new Source('name2', 'reference2', 'content2');
        $source3 = new Source('name3', 'reference3', 'content3');

        $collection->add($source1);
        $collection->add($source2);
        $collection->add($source3);

        $this->assertCount(3, $collection->all());
        $this->assertSame($source1, $collection->all()[0]);
        $this->assertSame($source2, $collection->all()[1]);
        $this->assertSame($source3, $collection->all()[2]);
    }

    public function testAllReturnsSourcesInOrder()
    {
        $collection = new SourceCollection();
        $sources = [
            new Source('first', 'ref1', 'content1'),
            new Source('second', 'ref2', 'content2'),
        ];

        foreach ($sources as $source) {
            $collection->add($source);
        }

        $this->assertSame($sources, $collection->all());
    }

    public function testCountReturnsZeroWhenNoSourcesAdded()
    {
        $collection = new SourceCollection();

        $this->assertCount(0, $collection);
    }

    public function testCountReturnsNumberOfAddedSources()
    {
        $collection = new SourceCollection();
        $collection->add(new Source('name1', 'reference1', 'content1'));
        $collection->add(new Source('name2', 'reference2', 'content2'));

        $this->assertCount(2, $collection);
    }

    public function testGetIteratorReturnsTraversableOfSources()
    {
        $collection = new SourceCollection();
        $source1 = new Source('name1', 'reference1', 'content1');
        $source2 = new Source('name2', 'reference2', 'content2');

        $collection->add($source1);
        $collection->add($source2);

        $iterator = $collection->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);

        $sources = iterator_to_array($iterator);
        $this->assertSame([$source1, $source2], $sources);
    }
}
