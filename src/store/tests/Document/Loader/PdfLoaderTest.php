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
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
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
        yield ['sample-heavy.pdf', 18];
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
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            'PDF file "{source}" contains no extractable text; skipping.',
            ['source' => $source],
        );

        $this->assertSame([], iterator_to_array((new PdfLoader(logger: $logger))->load($source)));
    }

    /**
     * @return iterable<array{string}>
     */
    public static function emptyFiles(): iterable
    {
        yield ['sample-empty.pdf'];
        yield ['sample-scanned.pdf'];
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

    public function testDirectoryContinuesAfterEmptyPdf()
    {
        $directory = sys_get_temp_dir().'/pdf-loader-'.bin2hex(random_bytes(8));
        mkdir($directory);
        copy(self::FIXTURES.'sample-empty.pdf', $directory.'/01-empty.pdf');
        copy(self::FIXTURES.'sample1.pdf', $directory.'/02-text.pdf');
        try {
            $loader = new DirectoryLoader(['pdf' => new PdfLoader()]);
            $documents = iterator_to_array($loader->load($directory));

            $this->assertCount(1, $documents);
            $this->assertSame(realpath($directory.'/02-text.pdf'), $documents[0]->getMetadata()->getSource());
            $this->assertStringContainsString('Lorem ipsum', $documents[0]->getContent());
            $this->assertSame(1, $documents[0]->getMetadata()['page_number']);
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
            $mapped = iterator_to_array((new SourceUrlTransformer($directory, 'https://example.com/pdf'))->transform($pages));
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
}
