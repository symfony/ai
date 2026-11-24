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

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use Smalot\PdfParser\Document;
use Smalot\PdfParser\Page;
use Smalot\PdfParser\Parser;
use Symfony\AI\Store\Document\Loader\DirectoryLoader;
use Symfony\AI\Store\Document\Loader\PdfLoader;
use Symfony\AI\Store\Document\Transformer\SourceUrlTransformer;
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Christopher X. Candreva <chris@westnet.com>
 */
final class PdfLoaderTest extends TestCase
{
    private const FIXTURES = __DIR__.'/../../Fixtures/pdf/';

    #[DataProvider('pdfFiles')]
    public function testExtraction(string $file, int $count)
    {
        $documents = iterator_to_array((new PdfLoader())->load(self::FIXTURES.$file));
        $this->assertCount($count, $documents);
        foreach ($documents as $index => $document) {
            $this->assertNotSame('', $document->getContent());
            $this->assertSame(trim($document->getContent()), $document->getContent());
            $this->assertSame($index + 1, $document->getMetadata()['page_number']);
            $this->assertSame($count, $document->getMetadata()['page_count']);
            $this->assertSame(self::FIXTURES.$file, $document->getMetadata()->getSource());
        }
    }

    /**
     * @return iterable<array{string, int}>
     */
    public static function pdfFiles(): iterable
    {
        yield ['sample1.pdf', 1];
        yield ['sample5.pdf', 5];
        yield ['sample10.pdf', 10];
        yield ['sample-form.pdf', 1];
        yield ['sample-table.pdf', 1];
        yield ['sample-landscape.pdf', 1];
    }

    public function testMetadataAndEmptyPageNumbering()
    {
        $documents = iterator_to_array((new PdfLoader())->load(self::FIXTURES.'controlled.pdf'));
        $this->assertCount(2, $documents);
        $this->assertSame("First page\nSecond paragraph", $documents[0]->getContent());
        $this->assertSame('Third page', $documents[1]->getContent());
        $this->assertSame(3, $documents[1]->getMetadata()['page_number']);
        $this->assertSame([
            '_source' => self::FIXTURES.'controlled.pdf',
            '_title' => 'Embedded title',
            'page_count' => 3,
            'pdf_author' => 'Alice; Bob',
            'pdf_subject' => 'XMP description',
            'pdf_keywords' => 'one; two',
            'pdf_creation_date' => '2024-01-02T03:04:05Z',
            'pdf_modification_date' => '2024-02-03T04:05:06Z',
            'page_number' => 1,
        ], $documents[0]->getMetadata()->getArrayCopy());
        $documents[0]->getMetadata()->setTitle('Changed');
        $this->assertSame('Embedded title', $documents[1]->getMetadata()->getTitle());
        $this->assertFalse($documents[1]->getMetadata()->hasParentId());
    }

    public function testMissingMetadataAndStableIdentity()
    {
        $loader = new PdfLoader();
        $source = self::FIXTURES.'untitled.pdf';
        $first = iterator_to_array($loader->load($source));
        $again = iterator_to_array($loader->load(realpath($source)));
        $this->assertSame('untitled.pdf', $first[0]->getMetadata()->getTitle());
        $this->assertCount(4, $first[0]->getMetadata());
        $this->assertSame($first[0]->getId(), $again[0]->getId());
        $this->assertInstanceOf(\Symfony\Component\Uid\UuidV5::class, Uuid::fromString($first[0]->getId()));
        $this->assertNotSame($first[0]->getId(), $first[1]->getId());
        $other = iterator_to_array($loader->load(self::FIXTURES.'controlled.pdf'));
        $this->assertNotSame($first[0]->getId(), $other[0]->getId());
    }

    public function testParserInjectionAndNormalization()
    {
        $page = $this->createMock(Page::class);
        $page->method('getText')->willReturn(' text ');
        $pdf = $this->createMock(Document::class);
        $pdf->method('getPages')->willReturn([$page]);
        $pdf->method('getDetails')->willReturn([
            'Title' => ['nested' => ['bad']], 'dc:title' => ' XMP title ',
            'Author' => 42, 'dc:creator' => [' Alice ', ['nested'], false, '', 'Bob'],
            'Subject' => 'PDF subject', 'dc:description' => 'Ignored',
            'Keywords' => [' ', []], 'pdf:keywords' => 'keywords',
            'CreationDate' => 'D:20240102', 'ModDate' => false,
            'Producer' => 'Not allowed',
        ]);
        $parser = $this->createMock(Parser::class);
        $parser->expects($this->once())->method('parseFile')->with(self::FIXTURES.'sample1.pdf')->willReturn($pdf);
        $documents = iterator_to_array((new PdfLoader($parser))->load(self::FIXTURES.'sample1.pdf'));
        $metadata = $documents[0]->getMetadata();
        $this->assertSame('XMP title', $metadata->getTitle());
        $this->assertSame('Alice; Bob', $metadata['pdf_author']);
        $this->assertSame('PDF subject', $metadata['pdf_subject']);
        $this->assertSame('keywords', $metadata['pdf_keywords']);
        $this->assertSame('D:20240102', $metadata['pdf_creation_date']);
        $this->assertArrayNotHasKey('pdf_modification_date', $metadata);
        $this->assertArrayNotHasKey('Producer', $metadata);
    }

    #[DataProvider('invalidSources')]
    public function testInvalidSource(?string $source)
    {
        $this->expectException(InvalidArgumentException::class);
        iterator_to_array((new PdfLoader())->load($source));
    }

    /**
     * @return iterable<array{?string}>
     */
    public static function invalidSources(): iterable
    {
        yield [null];
        yield [''];
        yield ['https://example.com/file.pdf'];
        yield ['file:///tmp/file.pdf'];
    }

    #[DataProvider('unreadableSources')]
    public function testMissingOrDirectory(string $source)
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($source);
        iterator_to_array((new PdfLoader())->load($source));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function unreadableSources(): iterable
    {
        yield [self::FIXTURES.'missing.pdf'];
        yield [self::FIXTURES];
    }

    public function testUnreadableFile()
    {
        $path = tempnam(sys_get_temp_dir(), 'pdf-loader-');
        chmod($path, 0000);
        try {
            if (is_readable($path)) {
                $this->markTestSkipped('The current user can read files without read permissions.');
            }
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('not a readable file');
            iterator_to_array((new PdfLoader())->load($path));
        } finally {
            chmod($path, 0600);
            unlink($path);
        }
    }

    #[DataProvider('emptyFiles')]
    public function testEmptyExtraction(string $file)
    {
        $source = self::FIXTURES.$file;
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('PDF file "%s" contains no extractable text.', $source));
        iterator_to_array((new PdfLoader())->load($source));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function emptyFiles(): iterable
    {
        yield ['sample-empty.pdf'];
    }

    #[DataProvider('brokenFiles')]
    public function testParserFailures(string $file)
    {
        try {
            iterator_to_array((new PdfLoader())->load(self::FIXTURES.$file));
            $this->fail('Expected parsing to fail.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString($file, $exception->getMessage());
            $this->assertNotNull($exception->getPrevious());
        }
    }

    /**
     * @return iterable<array{string}>
     */
    public static function brokenFiles(): iterable
    {
        yield ['README.md'];
        yield ['sample-protected.pdf'];
    }

    public function testPageExtractionFailure()
    {
        $failure = new \RuntimeException('Broken page');
        $page = $this->createMock(Page::class);
        $page->method('getText')->willThrowException($failure);
        $pdf = $this->createMock(Document::class);
        $pdf->method('getPages')->willReturn([$page]);
        $pdf->method('getDetails')->willReturn([]);
        $parser = $this->createMock(Parser::class);
        $parser->method('parseFile')->willReturn($pdf);
        try {
            iterator_to_array((new PdfLoader($parser))->load(self::FIXTURES.'sample1.pdf'));
            $this->fail('Expected extraction to fail.');
        } catch (RuntimeException $exception) {
            $this->assertSame($failure, $exception->getPrevious());
            $this->assertStringContainsString('sample1.pdf', $exception->getMessage());
        }
    }

    public function testDirectoryFailsOnEmptyPdf()
    {
        $directory = sys_get_temp_dir().'/pdf-loader-'.bin2hex(random_bytes(8));
        mkdir($directory);
        copy(self::FIXTURES.'sample-empty.pdf', $directory.'/01-empty.pdf');
        copy(self::FIXTURES.'sample1.pdf', $directory.'/02-text.pdf');
        try {
            $loader = new DirectoryLoader(['pdf' => new PdfLoader()]);
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('01-empty.pdf');
            iterator_to_array($loader->load($directory));
        } finally {
            unlink($directory.'/01-empty.pdf');
            unlink($directory.'/02-text.pdf');
            rmdir($directory);
        }
    }

    public function testDirectoryDelegationAndChunkMetadata()
    {
        $directory = sys_get_temp_dir().'/pdf-loader-'.bin2hex(random_bytes(8));
        mkdir($directory);
        copy(self::FIXTURES.'controlled.pdf', $directory.'/document.pdf');
        file_put_contents($directory.'/ignored.txt', 'ignored');
        try {
            $pages = iterator_to_array((new DirectoryLoader(['pdf' => new PdfLoader()]))->load($directory));
            $this->assertCount(2, $pages);
            $resolvedDirectory = realpath($directory);
            $this->assertNotFalse($resolvedDirectory);
            $mapped = iterator_to_array((new SourceUrlTransformer($resolvedDirectory, 'https://example.com/pdf'))->transform($pages));
            $this->assertSame($pages[0]->getId(), $mapped[0]->getId());
            $chunks = iterator_to_array((new TextSplitTransformer(8, 0))->transform($mapped));
            $this->assertGreaterThan(2, \count($chunks));
            foreach ($chunks as $chunk) {
                $metadata = $chunk->getMetadata();
                $this->assertSame('Embedded title', $metadata->getTitle());
                $this->assertSame('https://example.com/pdf/document.pdf', $metadata['source_url']);
                $this->assertContains($metadata['page_number'], [1, 3]);
                $this->assertSame(3, $metadata['page_count']);
                $this->assertSame($chunk->getContent(), $metadata->getText());
                $this->assertContains($metadata->getParentId(), array_map(static fn ($page) => $page->getId(), $pages));
            }
        } finally {
            unlink($directory.'/document.pdf');
            unlink($directory.'/ignored.txt');
            rmdir($directory);
        }
    }

    #[DataProvider('rejectedText')]
    public function testRejectsUnusableText(string $text, string $reason)
    {
        $source = self::FIXTURES.'sample1.pdf';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('PDF file "%s" page 1 has unusable extracted text: "%s".', $source, $reason));
        iterator_to_array((new PdfLoader($this->parserWithTexts([$text])))->load($source));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function rejectedText(): iterable
    {
        yield 'observed NUL counts' => [str_repeat("\0", 3078).str_repeat('a', 3716), 'NUL characters (3078)'];
        yield 'NUL at edges' => ["\0Readable text\0", 'NUL characters (2)'];
        yield 'only NUL' => ["\0", 'NUL characters (1)'];
        yield 'invalid UTF-8' => ["private text\xC3\x28", 'invalid UTF-8'];
        yield 'above boundary' => [str_repeat('a', 18)."\x01", 'excessive control characters (1 of 19 characters)'];
        yield 'Unicode character denominator' => [str_repeat('界', 18)."\u{0080}", 'excessive control characters (1 of 19 characters)'];
        yield 'DEL' => ["a\x7F", 'excessive control characters (1 of 2 characters)'];
    }

    #[DataProvider('usableText')]
    public function testPreservesUsableText(string $text)
    {
        $documents = iterator_to_array((new PdfLoader($this->parserWithTexts([$text])))->load(self::FIXTURES.'sample1.pdf'));

        $this->assertCount(1, $documents);
        $this->assertSame(trim($text), $documents[0]->getContent());
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function usableText(): iterable
    {
        yield 'at boundary' => [str_repeat('a', 19)."\x01"];
        yield 'below boundary' => [str_repeat('a', 20)."\x01"];
        yield 'Unicode boundary' => [str_repeat('界', 19)."\u{0080}"];
        yield 'whitespace' => ["a\t\n\r\f\v\u{0085}b"];
        yield 'multilingual and formatting' => ["Café e\u{0301} 中文 日本語 한국어 العربية فارسی می\u{200C}روم हिन्दी क्\u{200D}ष עברית \u{200F}שלום\u{200E} \u{2067}العربية\u{2069}"];
    }

    public function testLaterRejectedPageThrowsAfterEarlierPageWasYielded()
    {
        $source = self::FIXTURES.'sample1.pdf';
        $pages = (new PdfLoader($this->parserWithTexts(['First', "private text\0", 'Third'])))->load($source);
        $baseline = iterator_to_array((new PdfLoader($this->parserWithTexts(['First', 'Second', 'Third'])))->load($source));
        $this->assertInstanceOf(\Iterator::class, $pages);
        $pages->rewind();
        $this->assertSame($baseline[0]->getId(), $pages->current()->getId());
        $this->assertSame('First', $pages->current()->getContent());
        $this->assertSame($baseline[0]->getMetadata()->getArrayCopy(), $pages->current()->getMetadata()->getArrayCopy());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(\sprintf('PDF file "%s" page 2 has unusable extracted text: "NUL characters (1)".', $source));
        iterator_to_array($pages);
    }

    public function testEmptyPagesBeforeRejectionRetainPageNumber()
    {
        $loader = new PdfLoader($this->parserWithTexts(['', "\0", "\xFF"]));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('page 2 has unusable extracted text: "NUL characters (1)".');
        iterator_to_array($loader->load(self::FIXTURES.'sample1.pdf'));
    }

    public function testDirectoryFailsOnRejectedPdf()
    {
        $directory = sys_get_temp_dir().'/pdf-loader-'.bin2hex(random_bytes(8));
        mkdir($directory);
        copy(self::FIXTURES.'sample1.pdf', $directory.'/01-rejected.pdf');
        copy(self::FIXTURES.'sample1.pdf', $directory.'/02-valid.pdf');
        try {
            $rejectedParser = $this->parserWithTexts(["\0", "\xFF"]);
            $parser = $this->createMock(Parser::class);
            $parser->expects($this->once())->method('parseFile')->with(realpath($directory.'/01-rejected.pdf'))->willReturn($rejectedParser->parseFile($directory.'/01-rejected.pdf'));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('01-rejected.pdf" page 1 has unusable extracted text: "NUL characters (1)".');
            iterator_to_array((new DirectoryLoader(['pdf' => new PdfLoader($parser)]))->load($directory));
        } finally {
            unlink($directory.'/01-rejected.pdf');
            unlink($directory.'/02-valid.pdf');
            rmdir($directory);
        }
    }

    #[DataProvider('missingDependencies')]
    #[RunInSeparateProcess]
    #[PreserveGlobalState(false)]
    public function testMissingDependency(string $class, string $package)
    {
        $this->assertFalse(class_exists($class, false));
        $autoloaders = spl_autoload_functions();
        foreach ($autoloaders as $autoloader) {
            spl_autoload_unregister($autoloader);
        }
        spl_autoload_register(static function (string $requested) use ($class, $autoloaders): void {
            if ($class === $requested) {
                return;
            }
            foreach ($autoloaders as $autoloader) {
                $autoloader($requested);
            }
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer require '.$package);
        new PdfLoader();
    }

    /**
     * @return iterable<array{class-string, string}>
     */
    public static function missingDependencies(): iterable
    {
        yield [\Symfony\Component\Filesystem\Path::class, 'symfony/filesystem'];
        yield [Parser::class, 'smalot/pdfparser'];
    }

    /**
     * @param list<string> $texts
     */
    private function parserWithTexts(array $texts): Parser
    {
        $pages = [];
        foreach ($texts as $text) {
            $page = $this->createMock(Page::class);
            $page->method('getText')->willReturn($text);
            $pages[] = $page;
        }
        $pdf = $this->createMock(Document::class);
        $pdf->method('getPages')->willReturn($pages);
        $pdf->method('getDetails')->willReturn([]);
        $parser = $this->createMock(Parser::class);
        $parser->method('parseFile')->willReturn($pdf);

        return $parser;
    }
}
