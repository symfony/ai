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
use Symfony\AI\Store\Document\Loader\JsonFileLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\JsonPath\Exception\InvalidJsonStringInputException;

/**
 * @author Larry Sule-balogun <suleabimbola@gmail.com>
 */
final class JsonFileLoaderTest extends TestCase
{
    public function testLoadWithValidSource()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
            $metadata = [
                'title' => '$.articles[*].title',
                'author' => '$.articles[*].author',
            ],
        );

        $source = \dirname(__DIR__, 5).'/src/store/tests/Fixtures/articles.json';
        $documents = iterator_to_array($loader->load($source));

        $this->assertCount(2, $documents);
        $this->assertInstanceOf(TextDocument::class, $document1 = $documents[0]);
        $this->assertSame('a-001', $document1->getId());
        $this->assertStringStartsWith('Solar energy is a renewable', $document1->getContent());
        $this->assertSame('Introduction to Solar Energy', $document1->getMetadata()['title']);
        $this->assertSame('Paula Schmidt', $document1->getMetadata()['author']);
        $this->assertSame($source, $document1->getMetadata()['_source']);

        $this->assertInstanceOf(TextDocument::class, $document2 = $documents[1]);
        $this->assertSame('a-002', $document2->getId());
        $this->assertStringStartsWith('Wind power harnesses', $document2->getContent());
        $this->assertSame('Wind Power Basics', $document2->getMetadata()['title']);
        $this->assertSame('Paula Schmidt', $document2->getMetadata()['author']);
        $this->assertSame($source, $document2->getMetadata()['_source']);
    }

    public function testMetadataIsExtractedCorrectly()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
            $metadata = [
                'title' => '$.articles[*].title',
                'author' => '$.articles[*].author',
            ],
        );

        $source = \dirname(__DIR__, 5).'/src/store/tests/Fixtures/articles.json';
        $documents = iterator_to_array($loader->load($source));

        $this->assertCount(2, $documents);
        $this->assertSame('Introduction to Solar Energy', $documents[0]->getMetadata()['title']);
        $this->assertSame('Paula Schmidt', $documents[0]->getMetadata()['author']);
        $this->assertSame($source, $documents[0]->getMetadata()['_source']);

        $this->assertSame('Wind Power Basics', $documents[1]->getMetadata()['title']);
        $this->assertSame('Paula Schmidt', $documents[1]->getMetadata()['author']);
        $this->assertSame($source, $documents[1]->getMetadata()['_source']);
    }

    public function testLoadWithNullSource()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
            $metadata = [
                'title' => '$.articles[*].title',
                'author' => '$.articles[*].author',
            ],
        );

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JsonFileLoader requires a file path as source, null given.');

        iterator_to_array($loader->load(null));
    }

    public function testLoadWithInvalidSource()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
            $metadata = [
                'title' => '$.articles[*].title',
                'author' => '$.articles[*].author',
            ],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File "/invalid/source.txt" does not exist.');

        iterator_to_array($loader->load('/invalid/source.txt'));
    }

    public function testLoadWithInvalidJson()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
        );

        $this->expectException(InvalidJsonStringInputException::class);
        $this->expectExceptionMessageMatches('/Invalid JSON input: Syntax error/');

        iterator_to_array($loader->load(\dirname(__DIR__, 5).'/fixtures/lorem.txt'));
    }

    public function testLoadWithNoMatchingDocuments()
    {
        $loader = new JsonFileLoader(
            $id = '$.nonexistent[*].id',
            $content = '$.nonexistent[*].body',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('JsonPath expressions did not match any documents.');

        iterator_to_array($loader->load(\dirname(__DIR__, 5).'/src/store/tests/Fixtures/articles.json'));
    }

    public function testLoadWithMismatchedIdAndContentLength()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[0].body',
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ID and content JsonPath results must have the same length.');

        iterator_to_array($loader->load(\dirname(__DIR__, 5).'/src/store/tests/Fixtures/articles.json'));
    }

    public function testLoadWithNonScalarMetadata()
    {
        $loader = new JsonFileLoader(
            $id = '$.articles[*].id',
            $content = '$.articles[*].body',
            $metadata = [
                'tags' => '$.articles[*].tags',
            ],
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Metadata "tags" must resolve to a scalar value.');

        iterator_to_array($loader->load(\dirname(__DIR__, 5).'/src/store/tests/Fixtures/articles.json'));
    }
}
