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
use Symfony\AI\Platform\Result\ObjectResult;

final class ObjectResultTest extends TestCase
{
    public function testGetContentWithArray()
    {
        $result = new ObjectResult($expected = ['foo' => 'bar', 'baz' => ['qux']]);
        $this->assertSame($expected, $result->getContent());
    }

    public function testGetContentWithObject()
    {
        $result = new ObjectResult($expected = (object) ['foo' => 'bar', 'baz' => ['qux']]);
        $this->assertSame($expected, $result->getContent());
    }
}
