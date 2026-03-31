<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Toolbox\Attribute;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

final class AsToolTest extends TestCase
{
    public function testCanBeConstructed()
    {
        $attribute = new AsTool(
            name: 'name',
            description: 'description',
        );

        $this->assertSame('name', $attribute->name);
        $this->assertSame('description', $attribute->description);
        $this->assertNull($attribute->responseDescription);
    }

    public function testCanBeConstructedWithResponseDescription()
    {
        $attribute = new AsTool(
            name: 'name',
            description: 'description',
            responseDescription: 'Returns a list of items',
        );

        $this->assertSame('name', $attribute->name);
        $this->assertSame('description', $attribute->description);
        $this->assertSame('Returns a list of items', $attribute->responseDescription);
    }
}
