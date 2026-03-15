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
use Symfony\AI\Store\Document\EmbeddableDocumentInterface;
use Symfony\AI\Store\Document\Loader\RstToctreeLoader;
use Symfony\AI\Store\Document\TextDocument;
use Symfony\AI\Store\Exception\InvalidArgumentException;
use Symfony\AI\Store\Exception\RuntimeException;

final class RstToctreeLoaderTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = __DIR__.'/Fixtures/rst';
    }

    public function testLoadRequiresSource()
    {
        $loader = new RstToctreeLoader();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('RstToctreeLoader requires a file path as source, null given.');
        iterator_to_array($loader->load(), false);
    }

    public function testLoadThrowsForNonExistentFile()
    {
        $loader = new RstToctreeLoader();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File "/nonexistent/file.rst" does not exist.');
        iterator_to_array($loader->load('/nonexistent/file.rst'), false);
    }

    public function testLoadSimpleFlatRst()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/simple.rst'), false);

        $this->assertCount(3, $documents);
        foreach ($documents as $doc) {
            $this->assertInstanceOf(TextDocument::class, $doc);
        }
    }

    public function testLoadSimpleRstSectionTitles()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/simple.rst'), false);

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertContains('Introduction', $titles);
        $this->assertContains('Getting Started', $titles);
        $this->assertContains('Advanced Usage', $titles);
    }

    public function testLoadSimpleRstMetadataFields()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/simple.rst'), false);

        $source = $this->fixturesDir.'/simple.rst';
        foreach ($documents as $doc) {
            $this->assertSame($source, $doc->getMetadata()->getSource());
            $this->assertSame(0, $doc->getMetadata()->getDepth());
            $this->assertNotNull($doc->getMetadata()->getText());
        }
    }

    public function testLoadSimpleRstSectionContent()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/simple.rst'), false);

        $contentMap = [];
        foreach ($documents as $doc) {
            $title = $doc->getMetadata()->getTitle() ?? '';
            $contentMap[$title] = $doc->getContent();
        }

        $this->assertStringContainsString('overview of the topic', $contentMap['Introduction']);
        $this->assertStringContainsString('get started', $contentMap['Getting Started']);
        $this->assertStringContainsString('advanced features', $contentMap['Advanced Usage']);
    }

    public function testLoadRstWithToctree()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_toctree/index.rst'), false);

        // Should have sections from both index.rst and page.rst
        $this->assertGreaterThanOrEqual(2, \count($documents));

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertContains('Documentation Index', $titles);
        $this->assertContains('Component Overview', $titles);
    }

    public function testLoadToctreeIncreasesSectionDepth()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_toctree/index.rst'), false);

        $depthByTitle = [];
        foreach ($documents as $doc) {
            $title = $doc->getMetadata()->getTitle() ?? '';
            $depthByTitle[$title] = $doc->getMetadata()->getDepth();
        }

        // Root file sections at depth 0
        $this->assertSame(0, $depthByTitle['Documentation Index']);

        // Toctree referenced page at depth 1
        $this->assertSame(1, $depthByTitle['Component Overview']);
    }

    public function testLoadToctreePageSectionsHaveCorrectSource()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_toctree/index.rst'), false);

        $pageSource = $this->fixturesDir.'/with_toctree/page.rst';
        $pageDocs = array_filter(
            $documents,
            static fn (EmbeddableDocumentInterface $doc): bool => $doc->getMetadata()->getSource() === $pageSource,
        );

        $this->assertNotEmpty($pageDocs);
    }

    public function testLoadSetsTextMetadata()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/simple.rst'), false);

        foreach ($documents as $doc) {
            $this->assertTrue($doc->getMetadata()->hasText());
            $this->assertSame($doc->getContent(), $doc->getMetadata()->getText());
        }
    }

    public function testLoadToctreeWithAbsolutePaths()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_absolute_toctree/index.rst'), false);

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertContains('Main Documentation', $titles);
        $this->assertContains('Getting Started', $titles);
        $this->assertContains('Setup', $titles);
    }

    public function testLoadToctreeAbsolutePathResolvesFromDocRoot()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_absolute_toctree/index.rst'), false);

        $depthByTitle = [];
        foreach ($documents as $doc) {
            $title = $doc->getMetadata()->getTitle() ?? '';
            $depthByTitle[$title] = $doc->getMetadata()->getDepth();
        }

        // /guides/setup is referenced via absolute path from a depth-1 file
        $this->assertSame(0, $depthByTitle['Main Documentation']);
        $this->assertSame(1, $depthByTitle['Getting Started']);
        $this->assertSame(2, $depthByTitle['Setup']);
    }

    public function testLoadToctreeWithGlobPattern()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_glob_toctree/index.rst'), false);

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertContains('Topics Index', $titles);
        $this->assertContains('Alpha Topic', $titles);
        $this->assertContains('Beta Topic', $titles);
    }

    public function testLoadToctreeGlobDeduplicatesEntries()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_glob_toctree/index.rst'), false);

        // alpha.rst is listed explicitly AND matched by the glob — it should appear only once
        $alphaDocs = array_filter(
            $documents,
            static fn (EmbeddableDocumentInterface $doc): bool => 'Alpha Topic' === $doc->getMetadata()->getTitle(),
        );

        $this->assertCount(1, $alphaDocs);
    }

    public function testLoadToctreeWithTrailingSlashResolvesToIndex()
    {
        $loader = new RstToctreeLoader();
        $documents = iterator_to_array($loader->load($this->fixturesDir.'/with_trailing_slash_toctree/index.rst'), false);

        $titles = array_map(
            static fn (EmbeddableDocumentInterface $doc): string => $doc->getMetadata()->getTitle() ?? '',
            $documents,
        );

        $this->assertContains('Main Index', $titles);
        $this->assertContains('Components', $titles);
    }

    public function testLoadToctreeThrowsForMissingEntry()
    {
        $tempDir = sys_get_temp_dir().'/rst_missing_test_'.uniqid();
        mkdir($tempDir, 0777, true);
        file_put_contents($tempDir.'/index.rst', "Title\n=====\n\n.. toctree::\n\n   missing_page\n");

        try {
            $loader = new RstToctreeLoader();

            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('does not exist');
            iterator_to_array($loader->load($tempDir.'/index.rst'), false);
        } finally {
            unlink($tempDir.'/index.rst');
            rmdir($tempDir);
        }
    }

    public function testLoadSectionOverflowCreatesMultipleChunks()
    {
        // Create a temporary file with a very long section
        $tempFile = tempnam(sys_get_temp_dir(), 'rst_test_');
        $longText = str_repeat('This is a very long sentence that gets repeated many times. ', 300);
        file_put_contents($tempFile, "Long Section\n============\n\n".$longText);

        try {
            $loader = new RstToctreeLoader();
            $documents = iterator_to_array($loader->load($tempFile), false);

            // The section is longer than 15K chars, so it should be split
            $this->assertGreaterThan(1, \count($documents));

            // All chunks should have the same section title
            foreach ($documents as $doc) {
                $this->assertSame('Long Section', $doc->getMetadata()->getTitle());
            }

            // Overflow chunks should have parent_id set
            foreach ($documents as $doc) {
                $this->assertTrue($doc->getMetadata()->hasParentId());
            }
        } finally {
            unlink($tempFile);
        }
    }
}
