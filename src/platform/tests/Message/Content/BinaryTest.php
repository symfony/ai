<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Message\Content;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\File;

final class BinaryTest extends TestCase
{
    public function testCreateFromDataUrl()
    {
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $binary = File::fromDataUrl($dataUrl);

        $this->assertSame('image/png', $binary->getFormat());
        $this->assertNotEmpty($binary->asBinary());
        $this->assertSame('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', $binary->asBase64());
    }

    public function testThrowsExceptionForInvalidDataUrl()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid audio data URL format.');

        File::fromDataUrl('invalid-data-url');
    }

    public function testCreateFromFile()
    {
        $content = 'test file content';
        $filename = sys_get_temp_dir().'/binary-test-file.txt';
        file_put_contents($filename, $content);

        try {
            $binary = File::fromFile($filename);

            $this->assertSame('text/plain', $binary->getFormat());
            $this->assertSame($content, $binary->asBinary());
        } finally {
            unlink($filename);
        }
    }

    #[DataProvider('provideExistingFiles')]
    public function testCreateFromExistingFiles(string $filePath, string $expectedFormat)
    {
        $binary = File::fromFile($filePath);

        $this->assertSame($expectedFormat, $binary->getFormat());
        $this->assertNotEmpty($binary->asBinary());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideExistingFiles(): iterable
    {
        yield 'mp3' => [\dirname(__DIR__, 5).'/fixtures/audio.mp3', 'audio/mpeg'];
        yield 'jpg' => [\dirname(__DIR__, 5).'/fixtures/image.jpg', 'image/jpeg'];
    }

    public function testThrowsExceptionForNonExistentFile()
    {
        $this->expectException(\InvalidArgumentException::class);

        File::fromFile('/non/existent/file.jpg');
    }

    public function testConvertToDataUrl()
    {
        $data = 'Hello World';
        $format = 'text/plain';
        $binary = new File($data, $format);

        $dataUrl = $binary->asDataUrl();

        $this->assertSame('data:text/plain;base64,'.base64_encode($data), $dataUrl);
    }

    public function testRoundTripConversion()
    {
        $originalDataUrl = 'data:application/pdf;base64,JVBERi0xLjQKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmls';

        $binary = File::fromDataUrl($originalDataUrl);
        $resultDataUrl = $binary->asDataUrl();

        $this->assertSame($originalDataUrl, $resultDataUrl);
        $this->assertSame('application/pdf', $binary->getFormat());
        $this->assertSame('JVBERi0xLjQKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmls', $binary->asBase64());
    }
}
