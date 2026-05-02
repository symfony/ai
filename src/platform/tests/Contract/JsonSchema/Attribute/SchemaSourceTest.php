<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Contract\JsonSchema\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Contract\JsonSchema\Attribute\SchemaSource;
use Symfony\AI\Platform\Contract\JsonSchema\Provider\SchemaProviderInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;

final class SchemaSourceTest extends TestCase
{
    public function testStoresValidProviderFqcn()
    {
        $attribute = new SchemaSource(StatusProvider::class);

        $this->assertSame(StatusProvider::class, $attribute->provider);
    }

    public function testThrowsWhenProviderDoesNotImplementInterface()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(\sprintf('The provider "%s" must implement "%s".', \stdClass::class, SchemaProviderInterface::class));

        new SchemaSource(\stdClass::class); /* @phpstan-ignore-line argument.type */
    }
}
