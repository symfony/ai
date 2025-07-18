<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\TextResult;

#[CoversClass(TextResult::class)]
#[Small]
final class TextResultTest extends TestCase
{
    #[Test]
    public function getContent(): void
    {
        $result = new TextResult($expected = 'foo');
        self::assertSame($expected, $result->getContent());
    }
}
