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

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Loader\TextFileLoader;
use Symfony\AI\Store\Document\TextDocument;

#[CoversClass(TextFileLoader::class)]
final class TextFileLoaderTest extends TestCase
{
    public function testLoadWithNullSource()
    {
        $loader = new TextFileLoader();

        $this->expectException(\Symfony\AI\Store\Exception\InvalidArgumentException::class);
        $this->expectExceptionMessage('TextFileLoader requires a file path as source, null given.');

        iterator_to_array($loader->load(null));
    }

    public function testLoadWithInvalidSource()
    {
        $loader = new TextFileLoader();

        $this->expectException(\Symfony\AI\Store\Exception\RuntimeException::class);
        $this->expectExceptionMessage('File "/invalid/source.txt" does not exist.');

        iterator_to_array($loader->load('/invalid/source.txt'));
    }

    public function testLoadWithValidSource()
    {
        $loader = new TextFileLoader();

        $documents = iterator_to_array($loader->load(\dirname(__DIR__, 5).'/fixtures/lorem.txt'));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertStringStartsWith('Lorem ipsum', $document->content);
        $this->assertStringEndsWith('nonummy id, met', $document->content);
        $this->assertSame(1500, \strlen($document->content));
    }

    public function testSourceIsPresentInMetadata()
    {
        $loader = new TextFileLoader();

        $source = \dirname(__DIR__, 5).'/fixtures/lorem.txt';
        $documents = iterator_to_array($loader->load($source));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame($source, $document->metadata['_source']);
        $this->assertSame($source, $document->metadata->getSource());
    }
}
