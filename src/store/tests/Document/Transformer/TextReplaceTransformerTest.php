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

use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Document\Transformer\TextReplaceTransformer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class TextReplaceTransformerTest extends TestCase
{
    public function testReplaceWithConstructorParameters()
    {
        $transformer = new TextReplaceTransformer('foo', 'bar');
        $document = new TextDocument(Uuid::v4(), 'foo is foo');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('bar is bar', $result[0]->getContent());
    }

    public function testReplaceWithOptions()
    {
        $transformer = new TextReplaceTransformer('initial', 'value');
        $document = new TextDocument(Uuid::v4(), 'hello world');

        $result = iterator_to_array($transformer->transform([$document], [
            TextReplaceTransformer::OPTION_SEARCH => 'hello',
            TextReplaceTransformer::OPTION_REPLACE => 'goodbye',
        ]));

        $this->assertCount(1, $result);
        $this->assertSame('goodbye world', $result[0]->getContent());
    }

    public function testOptionsOverrideConstructorParameters()
    {
        $transformer = new TextReplaceTransformer('foo', 'bar');
        $document = new TextDocument(Uuid::v4(), 'foo hello');

        $result = iterator_to_array($transformer->transform([$document], [
            TextReplaceTransformer::OPTION_SEARCH => 'hello',
            TextReplaceTransformer::OPTION_REPLACE => 'world',
        ]));

        $this->assertCount(1, $result);
        $this->assertSame('foo world', $result[0]->getContent());
    }

    public function testReplaceMultipleOccurrences()
    {
        $transformer = new TextReplaceTransformer('a', 'b');
        $document = new TextDocument(Uuid::v4(), 'a a a');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('b b b', $result[0]->getContent());
    }

    public function testReplaceWithEmptyString()
    {
        $transformer = new TextReplaceTransformer('remove', '');
        $document = new TextDocument(Uuid::v4(), 'remove this word');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame(' this word', $result[0]->getContent());
    }

    public function testReplacePreservesMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $transformer = new TextReplaceTransformer('old', 'new');
        $document = new TextDocument(Uuid::v4(), 'old text', $metadata);

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('new text', $result[0]->getContent());
        $this->assertSame($metadata, $result[0]->getMetadata());
    }

    public function testReplacePreservesDocumentId()
    {
        $id = Uuid::v4();
        $transformer = new TextReplaceTransformer('old', 'new');
        $document = new TextDocument($id, 'old text');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame($id, $result[0]->getId());
    }

    public function testReplaceProcessesMultipleDocuments()
    {
        $transformer = new TextReplaceTransformer('x', 'y');
        $documents = [
            new TextDocument(Uuid::v4(), 'x marks the spot'),
            new TextDocument(Uuid::v4(), 'find x here'),
            new TextDocument(Uuid::v4(), 'no match'),
        ];

        $result = iterator_to_array($transformer->transform($documents));

        $this->assertCount(3, $result);
        $this->assertSame('y marks the spot', $result[0]->getContent());
        $this->assertSame('find y here', $result[1]->getContent());
        $this->assertSame('no match', $result[2]->getContent());
    }

    public function testReplaceCaseSensitive()
    {
        $transformer = new TextReplaceTransformer('Hello', 'Goodbye');
        $document = new TextDocument(Uuid::v4(), 'Hello hello HELLO');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('Goodbye hello HELLO', $result[0]->getContent());
    }

    public function testReplaceHandlesNoMatch()
    {
        $transformer = new TextReplaceTransformer('notfound', 'replacement');
        $document = new TextDocument(Uuid::v4(), 'original text');

        $result = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $result);
        $this->assertSame('original text', $result[0]->getContent());
    }

    public function testConstructorThrowsExceptionWhenSearchEqualsReplace()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search and replace strings must be different.');

        new TextReplaceTransformer('same', 'same');
    }

    public function testTransformThrowsExceptionWhenSearchEqualsReplaceInOptions()
    {
        $transformer = new TextReplaceTransformer('initial', 'value');
        $document = new TextDocument(Uuid::v4(), 'text');

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search and replace strings must be different.');

        iterator_to_array($transformer->transform([$document], [
            TextReplaceTransformer::OPTION_SEARCH => 'same',
            TextReplaceTransformer::OPTION_REPLACE => 'same',
        ]));
    }

    public function testEmptySearchAndReplaceThrowsException()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Search and replace strings must be different.');

        new TextReplaceTransformer('', '');
    }

    public function testPartialOptionsUseConstructorDefaults()
    {
        $transformer = new TextReplaceTransformer('default', 'replacement');
        $document = new TextDocument(Uuid::v4(), 'default text');

        // Only provide search option, should use constructor's replace value
        $result = iterator_to_array($transformer->transform([$document], [
            TextReplaceTransformer::OPTION_SEARCH => 'text',
        ]));

        $this->assertCount(1, $result);
        $this->assertSame('default replacement', $result[0]->getContent());
    }
}
