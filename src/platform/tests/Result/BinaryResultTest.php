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
use Symfony\AI\Platform\Result\BinaryResult;

final class BinaryResultTest extends TestCase
{
    public function testGetContent()
    {
        $result = new BinaryResult($expected = 'binary data');
        $this->assertSame($expected, $result->getContent());
    }

    public function testGetMimeType()
    {
        $result = new BinaryResult('binary data', $expected = 'image/png');
        $this->assertSame($expected, $result->getMimeType());
    }

    public function testGetMimeTypeReturnsNullWhenNotSet()
    {
        $result = new BinaryResult('binary data');
        $this->assertNull($result->getMimeType());
    }

    public function testToBase64()
    {
        $data = 'Hello World';
        $result = new BinaryResult($data);
        $this->assertSame(base64_encode($data), $result->toBase64());
    }

    public function testToDataUri()
    {
        $data = 'Hello World';
        $mimeType = 'text/plain';
        $result = new BinaryResult($data, $mimeType);
        $this->assertSame('data:text/plain;base64,'.base64_encode($data), $result->toDataUri());
    }

    public function testToDataUriThrowsExceptionWhenMimeTypeNotSet()
    {
        $result = new BinaryResult('binary data');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Mime type is not set.');

        $result->toDataUri();
    }

    public function testToDataUriWithMimeTypeExplicitlySet()
    {
        $result = new BinaryResult('binary data');
        $actual = $result->toDataUri('image/jpeg');
        $expected = 'data:image/jpeg;base64,'.base64_encode('binary data');

        $this->assertSame($expected, $actual);
    }
}
