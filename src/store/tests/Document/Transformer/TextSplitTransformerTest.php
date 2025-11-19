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
use Symfony\AI\Store\Document\Transformer\TextSplitTransformer;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

final class TextSplitTransformerTest extends TestCase
{
    private TextSplitTransformer $transformer;

    protected function setUp(): void
    {
        $this->transformer = new TextSplitTransformer();
    }

    public function testSplitReturnsSingleChunkForShortText()
    {
        $document = new TextDocument(Uuid::v4(), 'short text');

        $chunks = iterator_to_array($this->transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('short text', $chunks[0]->getContent());
    }

    public function testTextLength()
    {
        $this->assertSame(1500, mb_strlen($this->getLongText()));
    }

    public function testSplitSplitsLongTextWithOverlap()
    {
        $document = new TextDocument(Uuid::v4(), $this->getLongText());

        $chunks = iterator_to_array($this->transformer->transform([$document]));

        $this->assertCount(2, $chunks);

        $this->assertSame(1000, mb_strlen($chunks[0]->getContent()));
        $this->assertSame(substr($this->getLongText(), 0, 1000), $chunks[0]->getContent());

        $this->assertSame(700, mb_strlen($chunks[1]->getContent()));
        $this->assertSame(substr($this->getLongText(), 800, 700), $chunks[1]->getContent());
    }

    public function testSplitWithCustomChunkSizeAndOverlap()
    {
        $document = new TextDocument(Uuid::v4(), $this->getLongText());

        $chunks = iterator_to_array($this->transformer->transform([$document], [
            TextSplitTransformer::OPTION_CHUNK_SIZE => 150,
            TextSplitTransformer::OPTION_OVERLAP => 25,
        ]));

        $this->assertCount(12, $chunks);

        $this->assertSame(150, mb_strlen($chunks[0]->getContent()));
        $this->assertSame(substr($this->getLongText(), 0, 150), $chunks[0]->getContent());

        $this->assertSame(150, mb_strlen($chunks[1]->getContent()));
        $this->assertSame(substr($this->getLongText(), 125, 150), $chunks[1]->getContent());

        $this->assertSame(150, mb_strlen($chunks[2]->getContent()));
        $this->assertSame(substr($this->getLongText(), 250, 150), $chunks[2]->getContent());

        $this->assertSame(150, mb_strlen($chunks[3]->getContent()));
        $this->assertSame(substr($this->getLongText(), 375, 150), $chunks[3]->getContent());

        $this->assertSame(150, mb_strlen($chunks[4]->getContent()));
        $this->assertSame(substr($this->getLongText(), 500, 150), $chunks[4]->getContent());

        $this->assertSame(150, mb_strlen($chunks[5]->getContent()));
        $this->assertSame(substr($this->getLongText(), 625, 150), $chunks[5]->getContent());

        $this->assertSame(150, mb_strlen($chunks[6]->getContent()));
        $this->assertSame(substr($this->getLongText(), 750, 150), $chunks[6]->getContent());

        $this->assertSame(150, mb_strlen($chunks[7]->getContent()));
        $this->assertSame(substr($this->getLongText(), 875, 150), $chunks[7]->getContent());

        $this->assertSame(150, mb_strlen($chunks[8]->getContent()));
        $this->assertSame(substr($this->getLongText(), 1000, 150), $chunks[8]->getContent());

        $this->assertSame(150, mb_strlen($chunks[9]->getContent()));
        $this->assertSame(substr($this->getLongText(), 1125, 150), $chunks[9]->getContent());

        $this->assertSame(150, mb_strlen($chunks[10]->getContent()));
        $this->assertSame(substr($this->getLongText(), 1250, 150), $chunks[10]->getContent());

        $this->assertSame(125, mb_strlen($chunks[11]->getContent()));
        $this->assertSame(substr($this->getLongText(), 1375, 150), $chunks[11]->getContent());
    }

    public function testSplitWithZeroOverlap()
    {
        $document = new TextDocument(Uuid::v4(), $this->getLongText());

        $chunks = iterator_to_array($this->transformer->transform([$document], [
            TextSplitTransformer::OPTION_OVERLAP => 0,
        ]));

        $this->assertCount(2, $chunks);
        $this->assertSame(substr($this->getLongText(), 0, 1000), $chunks[0]->getContent());
        $this->assertSame(substr($this->getLongText(), 1000, 500), $chunks[1]->getContent());
    }

    public function testParentIdIsSetInMetadata()
    {
        $document = new TextDocument(Uuid::v4(), $this->getLongText());

        $chunks = iterator_to_array($this->transformer->transform([$document], [
            TextSplitTransformer::OPTION_CHUNK_SIZE => 1000,
            TextSplitTransformer::OPTION_OVERLAP => 200,
        ]));

        $this->assertCount(2, $chunks);
        $this->assertSame($document->getId(), $chunks[0]->getMetadata()['_parent_id']);
        $this->assertSame($document->getId(), $chunks[1]->getMetadata()['_parent_id']);
    }

    public function testMetadataIsInherited()
    {
        $document = new TextDocument(Uuid::v4(), $this->getLongText(), new Metadata([
            'key' => 'value',
            'foo' => 'bar',
        ]));

        $chunks = iterator_to_array($this->transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('value', $chunks[0]->getMetadata()['key']);
        $this->assertSame('bar', $chunks[0]->getMetadata()['foo']);
        $this->assertSame('value', $chunks[1]->getMetadata()['key']);
        $this->assertSame('bar', $chunks[1]->getMetadata()['foo']);
    }

    public function testSplitWithChunkSizeLargerThanText()
    {
        $document = new TextDocument(Uuid::v4(), 'tiny');

        $chunks = iterator_to_array($this->transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('tiny', $chunks[0]->getContent());
    }

    public function testSplitWithOverlapGreaterThanChunkSize()
    {
        $document = new TextDocument(Uuid::v4(), 'Abcdefg', new Metadata([]));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlap must be non-negative and less than chunk size.');

        iterator_to_array($this->transformer->transform([$document], [
            TextSplitTransformer::OPTION_CHUNK_SIZE => 10,
            TextSplitTransformer::OPTION_OVERLAP => 20,
        ]));
    }

    public function testSplitWithNegativeOverlap()
    {
        $document = new TextDocument(Uuid::v4(), 'Abcdefg', new Metadata([]));
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlap must be non-negative and less than chunk size.');

        iterator_to_array($this->transformer->transform([$document], [
            TextSplitTransformer::OPTION_CHUNK_SIZE => 10,
            TextSplitTransformer::OPTION_OVERLAP => -1,
        ]));
    }

    public function testConstructorWithValidParameters()
    {
        $transformer = new TextSplitTransformer(500, 100);
        $document = new TextDocument(Uuid::v4(), 'short text');

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('short text', $chunks[0]->getContent());
    }

    public function testConstructorWithDefaultParameters()
    {
        $transformer = new TextSplitTransformer();
        $document = new TextDocument(Uuid::v4(), 'short text');

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('short text', $chunks[0]->getContent());
    }

    public function testConstructorWithNegativeOverlap()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlap must be non-negative and less than chunk size. Got chunk size: 1000, overlap: -1');

        new TextSplitTransformer(1000, -1);
    }

    public function testConstructorWithOverlapEqualToChunkSize()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlap must be non-negative and less than chunk size. Got chunk size: 500, overlap: 500');

        new TextSplitTransformer(500, 500);
    }

    public function testConstructorWithOverlapGreaterThanChunkSize()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Overlap must be non-negative and less than chunk size. Got chunk size: 100, overlap: 200');

        new TextSplitTransformer(100, 200);
    }

    public function testConstructorParametersAreUsedAsDefaults()
    {
        $transformer = new TextSplitTransformer(150, 25);
        $document = new TextDocument(Uuid::v4(), $this->getLongText());

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(12, $chunks);
        $this->assertSame(150, mb_strlen($chunks[0]->getContent()));
    }

    private function getLongText(): string
    {
        return trim(file_get_contents(\dirname(__DIR__, 5).'/fixtures/lorem.txt'));
    }
}
