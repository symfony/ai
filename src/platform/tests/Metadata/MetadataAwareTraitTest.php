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

    public function testCloningGivesTheCopyItsOwnIndependentMetadata()
    {
        $original = $this->createTestClass();
        $original->getMetadata()->add('key', 'original value');

        $copy = clone $original;
        $copy->getMetadata()->add('key', 'copy value');

        $this->assertSame('original value', $original->getMetadata()->get('key'));
        $this->assertSame('copy value', $copy->getMetadata()->get('key'));
    }

    private function createTestClass(): object
    {
        return new class {
            use MetadataAwareTrait;
        };
    }
}
