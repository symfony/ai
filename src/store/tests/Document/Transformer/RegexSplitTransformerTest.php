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
use Symfony\AI\Store\Document\Transformer\RegexSplitTransformer;
use Symfony\Component\Uid\Uuid;

final class RegexSplitTransformerTest extends TestCase
{
    private const MARKDOWN_HEADING_PATTERN = '/^(#{1,6}\s+.+)$/m';

    public function testDocumentWithoutMatchIsPassedThroughUnchanged()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), 'plain text without any heading');

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame($document, $chunks[0]);
    }

    public function testSplitsOnMarkdownHeadings()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "# Title\nIntro text.\n## Section A\nContent A.\n## Section B\nContent B.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(3, $chunks);
        $this->assertSame("# Title\nIntro text.", $chunks[0]->getContent());
        $this->assertSame("## Section A\nContent A.", $chunks[1]->getContent());
        $this->assertSame("## Section B\nContent B.", $chunks[2]->getContent());
    }

    public function testTitleAndDepthMetadataAreSet()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "# Title\nIntro.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('# Title', $chunks[0]->getMetadata()->getTitle());
        $this->assertSame(1, $chunks[0]->getMetadata()['_depth']);
        $this->assertSame('## Section A', $chunks[1]->getMetadata()->getTitle());
        $this->assertSame(2, $chunks[1]->getMetadata()['_depth']);
    }

    public function testParentIdIsSetInMetadata()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "# Title\nIntro.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame($document->getId(), $chunks[0]->getMetadata()['_parent_id']);
        $this->assertSame($document->getId(), $chunks[1]->getMetadata()['_parent_id']);
    }

    public function testContentBeforeFirstMatchHasNoTitle()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "Leading text.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('Leading text.', $chunks[0]->getContent());
        $this->assertFalse($chunks[0]->getMetadata()->hasTitle());
        $this->assertSame('## Section A', $chunks[1]->getMetadata()->getTitle());
    }

    public function testKeepDelimiterFalseExcludesHeadingFromContent()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN, keepDelimiter: false);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('Content A.', $chunks[0]->getContent());
        $this->assertSame('## Section A', $chunks[0]->getMetadata()->getTitle());
    }

    public function testKeepDelimiterOptionOverridesConstructorDefault()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document], [
            RegexSplitTransformer::OPTION_KEEP_DELIMITER => false,
        ]));

        $this->assertCount(1, $chunks);
        $this->assertSame('Content A.', $chunks[0]->getContent());
    }

    public function testPatternOptionOverridesConstructorDefault()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "Section one\n---\nSection two");

        $chunks = iterator_to_array($transformer->transform([$document], [
            RegexSplitTransformer::OPTION_PATTERN => '/^(---)$/m',
        ]));

        $this->assertCount(2, $chunks);
        $this->assertSame('Section one', $chunks[0]->getContent());
        $this->assertSame("---\nSection two", $chunks[1]->getContent());
    }

    public function testEmptySectionsBetweenHeadingsAreSkipped()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## A\n## B\nContent B.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('## A', $chunks[0]->getContent());
        $this->assertSame("## B\nContent B.", $chunks[1]->getContent());
    }

    public function testMetadataIsInheritedFromSourceDocument()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent A.", new Metadata([
            Metadata::KEY_SOURCE => 'file.md',
        ]));

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('file.md', $chunks[0]->getMetadata()->getSource());
    }
}
