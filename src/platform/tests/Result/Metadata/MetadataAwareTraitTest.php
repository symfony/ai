<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result\Metadata;

use PHPUnit\Framework\Attributes\CoversTrait;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\Metadata\Metadata;
use Symfony\AI\Platform\Result\Metadata\MetadataAwareTrait;

#[CoversTrait(MetadataAwareTrait::class)]
#[Small]
#[UsesClass(Metadata::class)]
final class MetadataAwareTraitTest extends TestCase
{
    #[Test]
    public function itCanHandleMetadata(): void
    {
        $result = $this->createTestClass();
        $metadata = $result->getMetadata();

        self::assertCount(0, $metadata);

        $metadata->add('key', 'value');
        $metadata = $result->getMetadata();

        self::assertCount(1, $metadata);
    }

    private function createTestClass(): object
    {
        return new class {
            use MetadataAwareTrait;
        };
    }
}
