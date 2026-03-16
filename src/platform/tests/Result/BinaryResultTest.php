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
use Symfony\AI\Platform\Exception\IOException;
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

    public function testAsFile()
    {
        $data = 'binary file content';
        $result = new BinaryResult($data, 'image/png');
        $path = sys_get_temp_dir().'/symfony_ai_test_'.uniqid().'.png';

        try {
            $result->asFile($path);

            $this->assertFileExists($path);
            $this->assertSame($data, file_get_contents($path));
        } finally {
            if (file_exists($path)) {
                unlink($path);
            }
        }
    }

    public function testAsFileThrowsExceptionWhenDirectoryDoesNotExist()
    {
        $result = new BinaryResult('binary data');

        $this->expectException(IOException::class);
        $this->expectExceptionMessage('The directory "/non/existent/directory" does not exist.');

        $result->asFile('/non/existent/directory/file.png');
    }
}
