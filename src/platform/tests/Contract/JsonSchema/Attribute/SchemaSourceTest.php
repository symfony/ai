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
use Symfony\AI\Platform\Tests\Fixtures\JsonSchema\StatusProvider;

final class SchemaSourceTest extends TestCase
{
    public function testStoresProviderFqcn()
    {
        $attribute = new SchemaSource(StatusProvider::class);

        $this->assertSame(StatusProvider::class, $attribute->provider);
        $this->assertSame([], $attribute->context);
    }

    public function testAcceptsArbitraryServiceId()
    {
        $attribute = new SchemaSource('app.provider.status');

        $this->assertSame('app.provider.status', $attribute->provider);
    }

    public function testStoresContext()
    {
        $attribute = new SchemaSource(StatusProvider::class, ['entity' => 'PaintColor']);

        $this->assertSame(['entity' => 'PaintColor'], $attribute->context);
    }
}
