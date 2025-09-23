<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Tests\Document\Filter;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Store\Document\Filter\TextContainsFilter;
use Symfony\AI\Store\Document\Metadata;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\Component\Uid\Uuid;

/**
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
#[CoversClass(TextContainsFilter::class)]
final class TextContainsFilterTest extends TestCase
{
    public function testFilterWithConstructorParameters()
    {
        $filter = new TextContainsFilter('Week of Symfony');
        $documents = [
            new TextDocument(Uuid::v4(), 'This is a regular blog post'),
            new TextDocument(Uuid::v4(), 'Week of Symfony - News roundup'),
            new TextDocument(Uuid::v4(), 'Another regular post'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(2, $result);
        $this->assertSame('This is a regular blog post', $result[0]->content);
        $this->assertSame('Another regular post', $result[1]->content);
    }

    public function testFilterWithOptions()
    {
        $filter = new TextContainsFilter('initial');
        $documents = [
            new TextDocument(Uuid::v4(), 'Keep this document'),
            new TextDocument(Uuid::v4(), 'Remove this SPAM content'),
            new TextDocument(Uuid::v4(), 'Another good document'),
        ];

        $result = iterator_to_array($filter->filter($documents, [
            TextContainsFilter::OPTION_NEEDLE => 'SPAM',
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('Keep this document', $result[0]->content);
        $this->assertSame('Another good document', $result[1]->content);
    }

    public function testOptionsOverrideConstructorParameters()
    {
        $filter = new TextContainsFilter('Week of Symfony');
        $documents = [
            new TextDocument(Uuid::v4(), 'Regular post'),
            new TextDocument(Uuid::v4(), 'Week of Symfony news'),
            new TextDocument(Uuid::v4(), 'Advertisement content'),
        ];

        $result = iterator_to_array($filter->filter($documents, [
            TextContainsFilter::OPTION_NEEDLE => 'Advertisement',
        ]));

        $this->assertCount(2, $result);
        $this->assertSame('Regular post', $result[0]->content);
        $this->assertSame('Week of Symfony news', $result[1]->content);
    }

    public function testFilterCaseInsensitive()
    {
        $filter = new TextContainsFilter('SPAM', false);
        $documents = [
            new TextDocument(Uuid::v4(), 'This contains spam'),
            new TextDocument(Uuid::v4(), 'This contains SPAM'),
            new TextDocument(Uuid::v4(), 'This contains Spam'),
            new TextDocument(Uuid::v4(), 'Clean content'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(1, $result);
        $this->assertSame('Clean content', $result[0]->content);
    }

    public function testFilterCaseSensitive()
    {
        $filter = new TextContainsFilter('SPAM', true);
        $documents = [
            new TextDocument(Uuid::v4(), 'This contains spam'),
            new TextDocument(Uuid::v4(), 'This contains SPAM'),
            new TextDocument(Uuid::v4(), 'This contains Spam'),
            new TextDocument(Uuid::v4(), 'Clean content'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(3, $result);
        $this->assertSame('This contains spam', $result[0]->content);
        $this->assertSame('This contains Spam', $result[1]->content);
        $this->assertSame('Clean content', $result[2]->content);
    }

    public function testFilterWithCaseSensitivityOption()
    {
        $filter = new TextContainsFilter('test', false);
        $documents = [
            new TextDocument(Uuid::v4(), 'This has Test'),
            new TextDocument(Uuid::v4(), 'Clean content'),
        ];

        $result = iterator_to_array($filter->filter($documents, [
            TextContainsFilter::OPTION_CASE_SENSITIVE => true,
        ]));

        $this->assertCount(2, $result); // With case sensitivity, 'Test' != 'test'
        $this->assertSame('This has Test', $result[0]->content);
        $this->assertSame('Clean content', $result[1]->content);
    }

    public function testFilterPreservesMetadata()
    {
        $metadata = new Metadata(['key' => 'value']);
        $filter = new TextContainsFilter('remove');
        $documents = [
            new TextDocument(Uuid::v4(), 'keep this', $metadata),
            new TextDocument(Uuid::v4(), 'remove this content'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(1, $result);
        $this->assertSame('keep this', $result[0]->content);
        $this->assertSame($metadata, $result[0]->metadata);
    }

    public function testFilterPreservesDocumentId()
    {
        $id = Uuid::v4();
        $filter = new TextContainsFilter('remove');
        $documents = [
            new TextDocument($id, 'keep this content'),
            new TextDocument(Uuid::v4(), 'remove this content'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(1, $result);
        $this->assertSame($id, $result[0]->id);
    }

    public function testFilterWithEmptyDocuments()
    {
        $filter = new TextContainsFilter('anything');
        $documents = [];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(0, $result);
    }

    public function testFilterWithNoMatches()
    {
        $filter = new TextContainsFilter('nonexistent');
        $documents = [
            new TextDocument(Uuid::v4(), 'First document'),
            new TextDocument(Uuid::v4(), 'Second document'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(2, $result); // All documents should pass through
        $this->assertSame('First document', $result[0]->content);
        $this->assertSame('Second document', $result[1]->content);
    }

    public function testFilterWithAllMatches()
    {
        $filter = new TextContainsFilter('spam');
        $documents = [
            new TextDocument(Uuid::v4(), 'This is spam content'),
            new TextDocument(Uuid::v4(), 'More spam here'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(0, $result); // All documents should be filtered out
    }

    public function testFilterWithPartialMatch()
    {
        $filter = new TextContainsFilter('test');
        $documents = [
            new TextDocument(Uuid::v4(), 'This is a test document'),
            new TextDocument(Uuid::v4(), 'testing functionality'),
            new TextDocument(Uuid::v4(), 'Clean content'),
        ];

        $result = iterator_to_array($filter->filter($documents));

        $this->assertCount(1, $result);
        $this->assertSame('Clean content', $result[0]->content);
    }

    public function testPartialOptionsUseConstructorDefaults()
    {
        $filter = new TextContainsFilter('TEST', true);
        $documents = [
            new TextDocument(Uuid::v4(), 'This has test content'),
            new TextDocument(Uuid::v4(), 'Clean content'),
        ];

        // Only provide needle option, should use constructor's case sensitivity
        $result = iterator_to_array($filter->filter($documents, [
            TextContainsFilter::OPTION_NEEDLE => 'test',
        ]));

        $this->assertCount(1, $result); // Case sensitive, 'test' found in first document, so it's filtered out
        $this->assertSame('Clean content', $result[0]->content);
    }

    #[TestWith([''])]
    #[TestWith(['   '])]
    #[TestWith(["\t\n\r "])]
    public function testConstructorThrowsExceptionForInvalidNeedle(string $needle)
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Needle cannot be an empty string.');

        new TextContainsFilter($needle);
    }

    public function testConstructorAcceptsValidNeedle()
    {
        $filter = new TextContainsFilter('valid needle');

        $this->assertInstanceOf(TextContainsFilter::class, $filter);
    }
}
