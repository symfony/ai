<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Transformer;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\SourceUrlTransformer;
use Symfony\AI\Store\Exception\InvalidArgumentException;

/**
 * @author Christopher X. Candreva <chris@westnet.com>
 */
final class SourceUrlTransformerTest extends TestCase
{
    #[DataProvider('matchingPaths')]
    public function testMapping(string $prefix, string $source, string $url)
    {
        $metadata = new Metadata(['_source' => $source, 'custom' => 'value']);
        $document = new TextDocument('id', 'text', $metadata);
        $result = iterator_to_array((new SourceUrlTransformer($prefix, 'https://example.com/files///'))->transform([$document]))[0];
        $this->assertNotSame($document, $result);
        $this->assertNotSame($metadata, $result->getMetadata());
        $this->assertSame('id', $result->getId());
        $this->assertSame('text', $result->getContent());
        $this->assertSame($source, $result->getMetadata()->getSource());
        $this->assertSame('value', $result->getMetadata()['custom']);
        $this->assertSame($url, $result->getMetadata()['source_url']);
        $this->assertArrayNotHasKey('source_url', $metadata);
    }

    /**
     * @return iterable<array{string, string, string}>
     */
    public static function matchingPaths(): iterable
    {
        yield ['/files', '/files/a.pdf', 'https://example.com/files/a.pdf'];
        yield ['/files/', '/files/sub/../a.pdf', 'https://example.com/files/a.pdf'];
        yield ['/files/./', '/files/sub/a b é#?.pdf', 'https://example.com/files/sub/a%20b%20%C3%A9%23%3F.pdf'];
        yield ['/files', '/files/100%.pdf', 'https://example.com/files/100%25.pdf'];
        yield ['/', '/a.pdf', 'https://example.com/files/a.pdf'];
        if ('\\' === \DIRECTORY_SEPARATOR) {
            yield ['C:\\files\\', 'C:\\files\\sub\\a.pdf', 'https://example.com/files/sub/a.pdf'];
        }
        yield [getcwd(), './a.pdf', 'https://example.com/files/a.pdf'];
    }

    /**
     * @param array{_source?: mixed, source_url?: mixed} $values
     */
    #[DataProvider('unchangedMetadata')]
    public function testUnmatchedDocumentsAreUnchanged(array $values)
    {
        $document = new TextDocument('id', 'text', new Metadata($values));
        $result = iterator_to_array((new SourceUrlTransformer('/files', 'https://example.com'))->transform([$document]))[0];
        $this->assertSame($document, $result);
    }

    /**
     * @return iterable<array{array{_source?: mixed, source_url?: mixed}}>
     */
    public static function unchangedMetadata(): iterable
    {
        yield [[]];
        yield [['_source' => '/files-other/a.pdf']];
        yield [['_source' => '/files/../outside.pdf']];
        yield [['_source' => '/files']];
        yield [['_source' => 'https://example.com/files/a.pdf']];
        yield [['_source' => 'file:///files/a.pdf']];
        yield [['_source' => null]];
        yield [['_source' => 123]];
        yield [['_source' => '']];
        yield [['_source' => '/files/a.pdf', 'source_url' => 'https://old.example/a']];
        yield [['_source' => '/files/a.pdf', 'source_url' => null]];
    }

    #[DataProvider('invalidPrefixes')]
    public function testInvalidConfiguration(string $path, string $url)
    {
        $this->expectException(InvalidArgumentException::class);
        new SourceUrlTransformer($path, $url);
    }

    /**
     * @return iterable<array{string, string}>
     */
    public static function invalidPrefixes(): iterable
    {
        yield ['relative', 'https://example.com'];
        yield ['', 'https://example.com'];
        yield ['https://example.com/files', 'https://example.com'];
        yield ['/files', '/relative'];
        yield ['/files', 'ftp://example.com'];
        yield ['/files', 'https://example.com?query'];
        yield ['/files', 'https://example.com#fragment'];
        yield ['/files', 'https://example.com?'];
        yield ['/files', 'https://example.com#'];
        yield ['/files', 'https://'];
    }
}
