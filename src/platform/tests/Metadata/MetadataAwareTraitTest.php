<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Metadata;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;

#[CoversTrait(MetadataAwareTrait::class)]
final class MetadataAwareTraitTest extends TestCase
{
    public function testItCanHandleMetadata()
    {
        $result = $this->createTestClass();
        $metadata = $result->getMetadata();

        $this->assertInstanceOf(Metadata::class, $metadata);
        $this->assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $result->getMetadata();

        $this->assertCount(1, $metadata);
    }

    public function testClonedObjectHasIndependentMetadata()
    {
        $original = $this->createTestClass();
        $original->getMetadata()->add('key', 'value');

        $clone = clone $original;
        $clone->getMetadata()->add('key', 'changed');
        $clone->getMetadata()->add('other', 'added');

        $this->assertSame('value', $original->getMetadata()->get('key'));
        $this->assertFalse($original->getMetadata()->has('other'));
        $this->assertSame('changed', $clone->getMetadata()->get('key'));
    }

    public function testClonedObjectWithoutMetadataStaysIndependent()
    {
        $original = $this->createTestClass();

        $clone = clone $original;
        $clone->getMetadata()->add('key', 'value');

        $this->assertCount(0, $original->getMetadata());
    }

    private function createTestClass(): object
    {
        return new class {
            use MetadataAwareTrait;
        };
    }
}
