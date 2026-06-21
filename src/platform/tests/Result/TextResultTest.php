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
use Symfony\AI\Platform\Result\TextResult;

final class TextResultTest extends TestCase
{
    public function testGetContent()
    {
        $result = new TextResult($expected = 'foo');
        $this->assertSame($expected, $result->getContent());
    }

    public function testWithContentReturnsNewInstanceWithUpdatedContent()
    {
        $original = new TextResult('foo', 'signature');
        $original->getMetadata()->add('key', 'value');

        $modified = $original->withContent('bar');

        $this->assertNotSame($original, $modified);
        $this->assertSame('bar', $modified->getContent());
        $this->assertSame('signature', $modified->getSignature());
        $this->assertSame(['key' => 'value'], $modified->getMetadata()->all());
        $this->assertSame('foo', $original->getContent());
    }

    public function testWithContentReturnsSameInstanceWhenContentUnchanged()
    {
        $original = new TextResult('foo');

        $this->assertSame($original, $original->withContent('foo'));
    }
}
