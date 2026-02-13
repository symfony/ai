<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Loader;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Loader\MarkdownLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

final class MarkdownLoaderTest extends TestCase
{
    public function testLoadWithNullSource()
    {
        $loader = new MarkdownLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('MarkdownLoader requires a file path as source, null given.');
        $this->expectExceptionCode(0);
        iterator_to_array($loader->load());
    }

    public function testLoadWithInvalidSource()
    {
        $loader = new MarkdownLoader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File "/invalid/source.md" does not exist.');
        $this->expectExceptionCode(0);
        iterator_to_array($loader->load('/invalid/source.md'));
    }

    public function testLoadPreservesMarkdownByDefault()
    {
        $file = $this->createTempFile("# Hello\n\nThis is **bold** text.");

        $loader = new MarkdownLoader();
        $documents = iterator_to_array($loader->load($file));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame("# Hello\n\nThis is **bold** text.", $document->getContent());
        $this->assertSame('Hello', $document->getMetadata()['title']);
        $this->assertSame($file, $document->getMetadata()->getSource());
    }

    public function testLoadWithStripFormattingOption()
    {
        $file = $this->createTempFile("# Hello\n\nThis is **bold** and [a link](https://example.com).");

        $loader = new MarkdownLoader();
        $documents = iterator_to_array($loader->load($file, [
            'strip_formatting' => true,
        ]));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame("Hello\n\nThis is bold and a link.", $document->getContent());
    }

    public function testLoadEmptyFileYieldsNothing()
    {
        $file = $this->createTempFile('   ');

        $loader = new MarkdownLoader();
        $documents = iterator_to_array($loader->load($file));

        $this->assertCount(0, $documents);
    }

    public function testTitleExtractionFromFirstHeading()
    {
        $file = $this->createTempFile("## Getting Started\n\nSome content.");

        $loader = new MarkdownLoader();
        $documents = iterator_to_array($loader->load($file));

        $this->assertCount(1, $documents);
        $this->assertFalse($documents[0]->getMetadata()->offsetExists('title'));
    }

    public function testSourceIsPresentInMetadata()
    {
        $file = $this->createTempFile('# Test');

        $loader = new MarkdownLoader();
        $documents = iterator_to_array($loader->load($file));

        $this->assertCount(1, $documents);
        $this->assertSame($file, $documents[0]->getMetadata()->getSource());
    }

    private function createTempFile(string $content): string
    {
        $fs = new Filesystem();

        $file = $fs->tempnam(sys_get_temp_dir(), 'markdown_loader_test_');

        $fs->dumpFile($file, $content);

        return $file;
    }
}
