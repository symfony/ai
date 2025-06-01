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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Toolbox\Attribute\AsTool;

#[CoversClass(AsTool::class)]
final class AsToolTest extends TestCase
{
    #[Test]
    public function canBeConstructed(): void
    {
        $attribute = new AsTool(
            name: 'name',
            description: 'description',
        );

        self::assertSame('name', $attribute->name);
        self::assertSame('description', $attribute->description);
    }
}
