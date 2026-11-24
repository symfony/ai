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
use Symfony\AI\Store\Document\Loader\PdfLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;

final class PdfLoaderTest extends TestCase
{
    public function testLoadWithNullSource()
    {
        $loader = new PdfLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('PdfLoader requires a file path as source, null given.');
        $this->expectExceptionCode(0);
        iterator_to_array($loader->load());
    }

    public function testLoadAsMarkdown()
    {
        $loader = new PdfLoader();
        $documents = iterator_to_array($loader->load(__DIR__.'/Fixtures/pdf/simplemarkdown.pdf'));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame(
            <<<MARKDOWN
            **Bold text**
            *Italic text*
            ***Bold and italic text***
            [Link](http://Example.com/)
            MARKDOWN,
            $document->getContent()
        );
    }

    public function testLoadAsText()
    {
        $loader = new PdfLoader(false);
        $documents = iterator_to_array($loader->load(__DIR__.'/Fixtures/pdf/simplemarkdown.pdf'));

        $this->assertCount(1, $documents);
        $this->assertInstanceOf(TextDocument::class, $document = $documents[0]);
        $this->assertSame(
            <<<MARKDOWN
            Bold text
            Italic text
            Bold and italic text
            Link
            MARKDOWN,
            $document->getContent()
        );
    }
}
