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
use Symfony\AI\Platform\Result\StreamResult;

#[CoversClass(StreamResultTest::class)]
#[Small]
final class StreamResultTest extends TestCase
{
    #[Test]
    public function getContent(): void
    {
        $generator = (function () {
            yield 'data1';
            yield 'data2';
        })();

        $result = new StreamResult($generator);
        self::assertInstanceOf(\Generator::class, $result->getContent());

        $content = iterator_to_array($result->getContent());

        self::assertCount(2, $content);
        self::assertSame('data1', $content[0]);
        self::assertSame('data2', $content[1]);
    }
}
