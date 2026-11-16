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
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;
use Symfony\Component\Uid\Uuid;

final class RegexSplitTransformerTest extends TestCase
{
    private const MARKDOWN_HEADING_PATTERN = '/^#{1,6}[ \t]+.+$/m';

    public function testDocumentWithoutMatchIsPassedThroughWithText()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), 'plain text without any heading', new Metadata(['key' => 'value']));

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame($document->getId(), $chunks[0]->getId());
        $this->assertSame('plain text without any heading', $chunks[0]->getContent());
        $this->assertSame('plain text without any heading', $chunks[0]->getMetadata()->getText());
        $this->assertSame('value', $chunks[0]->getMetadata()['key']);
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
        $this->assertSame('Title', $chunks[0]->getMetadata()->getTitle());
        $this->assertSame(1, $chunks[0]->getMetadata()['_depth']);
        $this->assertSame('Section A', $chunks[1]->getMetadata()->getTitle());
        $this->assertSame(2, $chunks[1]->getMetadata()['_depth']);
    }

    public function testBareHashLineIsNotTreatedAsHeading()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "Intro.\n#\n\nfoo\nbar");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame($document->getId(), $chunks[0]->getId());
        $this->assertFalse($chunks[0]->getMetadata()->hasTitle());
    }

    public function testDelimiterSpanningLinesHasNoTitle()
    {
        $transformer = new RegexSplitTransformer('/^(#\n.+)$/m');
        $document = new TextDocument(Uuid::v4(), "Intro.\n#\nfoo\nbar");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertNull($chunks[1]->getMetadata()->getTitle());
        $this->assertArrayNotHasKey('_depth', $chunks[1]->getMetadata());
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
        $this->assertSame('Section A', $chunks[1]->getMetadata()->getTitle());
    }

    public function testKeepDelimiterFalseExcludesHeadingFromContent()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN, keepDelimiter: false);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame('Content A.', $chunks[0]->getContent());
        $this->assertSame('Section A', $chunks[0]->getMetadata()->getTitle());
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
        $this->assertFalse($chunks[1]->getMetadata()->hasTitle());
        $this->assertFalse($chunks[1]->getMetadata()->hasDepth());
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

    public function testDocumentWithOnlyHeadingsIsPassedThroughWithoutDelimiter()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN, false);
        $document = new TextDocument(Uuid::v4(), "## A\n## B");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame($document->getId(), $chunks[0]->getId());
        $this->assertSame("## A\n## B", $chunks[0]->getContent());
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

    public function testChunksWithoutHeadingKeepSourceTitleAndDepth()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "Lead\n## A\nx", new Metadata([
            Metadata::KEY_TITLE => 'Parent',
            Metadata::KEY_DEPTH => 1,
        ]));

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('Parent', $chunks[0]->getMetadata()->getTitle());
        $this->assertSame(1, $chunks[0]->getMetadata()->getDepth());
        $this->assertSame('A', $chunks[1]->getMetadata()->getTitle());
        $this->assertSame(2, $chunks[1]->getMetadata()->getDepth());
    }

    public function testNonHeadingDelimiterKeepsSourceTitleAndDepth()
    {
        $transformer = new RegexSplitTransformer('/^(---)$/m');
        $document = new TextDocument(Uuid::v4(), "Section one\n---\nSection two", new Metadata([
            Metadata::KEY_TITLE => 'Parent',
            Metadata::KEY_DEPTH => 1,
        ]));

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        foreach ($chunks as $chunk) {
            $this->assertSame('Parent', $chunk->getMetadata()->getTitle());
            $this->assertSame(1, $chunk->getMetadata()->getDepth());
        }
    }

    public function testTextMetadataIsSetOnEachChunk()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "# Title\nIntro.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('# Title'."\n".'Intro.', $chunks[0]->getMetadata()->getText());
        $this->assertSame('## Section A'."\n".'Content A.', $chunks[1]->getMetadata()->getText());
    }

    public function testClosingHashesAreStrippedFromTitle()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## Section A ##\nContent A.\n## Using C#\nContent B.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame('Section A', $chunks[0]->getMetadata()->getTitle());
        $this->assertSame('Using C#', $chunks[1]->getMetadata()->getTitle());
    }

    public function testConstructorThrowsOnInvalidPattern()
    {
        $this->expectException(InvalidArgumentException::class);

        new RegexSplitTransformer('/(unterminated');
    }

    public function testPatternWithoutCapturingGroupSplitsOnWholeMatch()
    {
        $transformer = new RegexSplitTransformer('/^#+ .+$/m');
        $document = new TextDocument(Uuid::v4(), "Intro.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame("## Section A\nContent A.", $chunks[1]->getContent());
        $this->assertSame('Section A', $chunks[1]->getMetadata()->getTitle());
    }

    public function testPatternWithMultipleCapturingGroupsSplitsOnWholeMatch()
    {
        $transformer = new RegexSplitTransformer('/^(#+) (.+)$/m');
        $document = new TextDocument(Uuid::v4(), "Intro.\n## Section A\nContent A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(2, $chunks);
        $this->assertSame("## Section A\nContent A.", $chunks[1]->getContent());
        $this->assertSame(2, $chunks[1]->getMetadata()->getDepth());
    }

    public function testTransformThrowsOnInvalidPatternOption()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent A.");

        $this->expectException(InvalidArgumentException::class);

        iterator_to_array($transformer->transform([$document], [
            RegexSplitTransformer::OPTION_PATTERN => '/(unterminated',
        ]));
    }

    public function testSplitsContentThatIsNotValidUtf8()
    {
        $transformer = new RegexSplitTransformer(self::MARKDOWN_HEADING_PATTERN);
        $document = new TextDocument(Uuid::v4(), "## Section A\nContent \xFF A.");

        $chunks = iterator_to_array($transformer->transform([$document]));

        $this->assertCount(1, $chunks);
        $this->assertSame("## Section A\nContent \xFF A.", $chunks[0]->getContent());
    }

    public function testTransformThrowsWhenSplittingFails()
    {
        $transformer = new RegexSplitTransformer('/((?:\D+|<\d+>)*[!?])/');
        $document = new TextDocument(Uuid::v4(), 'foobar foobar foobar');

        $backtrackLimit = ini_set('pcre.backtrack_limit', '1');
        $jit = ini_set('pcre.jit', '0');

        try {
            $this->expectException(RuntimeException::class);

            iterator_to_array($transformer->transform([$document]));
        } finally {
            ini_set('pcre.backtrack_limit', (string) $backtrackLimit);
            ini_set('pcre.jit', (string) $jit);
        }
    }
}
