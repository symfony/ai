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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\StreamResult;

final class StreamResultTest extends TestCase
{
    public function testGetContent()
    {
        $generator = (function () {
            yield 'data1';
            yield 'data2';
        })();

        $result = new StreamResult($generator);
        $this->assertInstanceOf(\Generator::class, $result->getContent());

        $content = iterator_to_array($result->getContent());

        $this->assertCount(2, $content);
        $this->assertSame('data1', $content[0]);
        $this->assertSame('data2', $content[1]);
    }
}
