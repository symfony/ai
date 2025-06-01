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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Small;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\Content\File;

#[CoversClass(File::class)]
#[Small]
final class BinaryTest extends TestCase
{
    #[Test]
    public function createFromDataUrl(): void
    {
        $dataUrl = 'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=';

        $binary = File::fromDataUrl($dataUrl);

        self::assertSame('image/png', $binary->getFormat());
        self::assertNotEmpty($binary->asBinary());
        self::assertSame('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', $binary->asBase64());
    }

    #[Test]
    public function throwsExceptionForInvalidDataUrl(): void
    {
        self::expectException(InvalidArgumentException::class);
        self::expectExceptionMessage('Invalid audio data URL format.');

        File::fromDataUrl('invalid-data-url');
    }

    #[Test]
    public function createFromFile(): void
    {
        $content = 'test file content';
        $filename = sys_get_temp_dir().'/binary-test-file.txt';
        file_put_contents($filename, $content);

        try {
            $binary = File::fromFile($filename);

            self::assertSame('text/plain', $binary->getFormat());
            self::assertSame($content, $binary->asBinary());
        } finally {
            unlink($filename);
        }
    }

    #[Test]
    #[DataProvider('provideExistingFiles')]
    public function createFromExistingFiles(string $filePath, string $expectedFormat): void
    {
        $binary = File::fromFile($filePath);

        self::assertSame($expectedFormat, $binary->getFormat());
        self::assertNotEmpty($binary->asBinary());
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function provideExistingFiles(): iterable
    {
        yield 'mp3' => [\dirname(__DIR__, 3).'/Fixture/audio.mp3', 'audio/mpeg'];
        yield 'jpg' => [\dirname(__DIR__, 3).'/Fixture/image.jpg', 'image/jpeg'];
    }

    #[Test]
    public function throwsExceptionForNonExistentFile(): void
    {
        self::expectException(\InvalidArgumentException::class);

        File::fromFile('/non/existent/file.jpg');
    }

    #[Test]
    public function convertToDataUrl(): void
    {
        $data = 'Hello World';
        $format = 'text/plain';
        $binary = new File($data, $format);

        $dataUrl = $binary->asDataUrl();

        self::assertSame('data:text/plain;base64,'.base64_encode($data), $dataUrl);
    }

    #[Test]
    public function roundTripConversion(): void
    {
        $originalDataUrl = 'data:application/pdf;base64,JVBERi0xLjQKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmls';

        $binary = File::fromDataUrl($originalDataUrl);
        $resultDataUrl = $binary->asDataUrl();

        self::assertSame($originalDataUrl, $resultDataUrl);
        self::assertSame('application/pdf', $binary->getFormat());
        self::assertSame('JVBERi0xLjQKJcfsj6IKNSAwIG9iago8PC9MZW5ndGggNiAwIFIvRmls', $binary->asBase64());
    }
}
