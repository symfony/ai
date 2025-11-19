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
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;

#[CoversTrait(MetadataAwareTrait::class)]
final class MetadataAwareTraitTest extends TestCase
{
    public function testItCanHandleMetadata()
    {
        $result = $this->createTestClass();
        $metadata = $result->getMetadata();

        $this->assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $result->getMetadata();

        $this->assertCount(1, $metadata);
    }

    private function createTestClass(): object
    {
        return new class {
            use MetadataAwareTrait;
        };
    }
}
