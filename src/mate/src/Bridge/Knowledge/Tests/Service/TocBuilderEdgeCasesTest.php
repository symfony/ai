<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Service;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Model\TocNode;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;

/**
 * Covers toctree edge cases: ``Title <path>`` aliases, absolute entries,
 * missing files (silently skipped), duplicate entries, and glob patterns.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TocBuilderEdgeCasesTest extends TestCase
{
    private string $docsDir;

    protected function setUp(): void
    {
        $this->docsDir = \dirname(__DIR__).'/Fixtures/docs-edge';
    }

    public function testTitleAliasSyntaxResolvesToTargetFile()
    {
        $root = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);

        $node = $this->findChildByPath($root, 'aliased');

        $this->assertNotNull($node, 'Aliased entry "Aliased Title <aliased>" should resolve to aliased.rst');
        $this->assertSame('Real Aliased Title', $node->getTitle());
    }

    public function testAbsoluteToctreeEntryIsResolvedFromDocsRoot()
    {
        $root = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);

        $node = $this->findChildByPath($root, 'absolute');

        $this->assertNotNull($node, 'Absolute toctree entry "/absolute" should be resolved relative to the docs root');
        $this->assertSame('Absolute Entry', $node->getTitle());
    }

    public function testMissingFileEntryIsSilentlyDroppedFromToc()
    {
        $root = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);

        $this->assertNull(
            $this->findChildByPath($root, 'missing-page'),
            'Missing toctree entry "missing-page" must not appear in the TOC',
        );
    }

    public function testDuplicateEntryIsIncludedOnlyOnce()
    {
        $root = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);

        $duplicates = array_filter(
            $root->getChildren(),
            static fn (TocNode $child) => 'duplicate' === $child->getPath(),
        );

        $this->assertCount(1, $duplicates, 'A repeated toctree entry must be deduplicated.');
    }

    public function testGlobEntryPullsInAllMatchingFiles()
    {
        $root = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);

        $globPaths = array_values(array_filter(
            array_map(static fn (TocNode $child) => $child->getPath(), $root->getChildren()),
            static fn (string $path) => str_starts_with($path, 'glob/'),
        ));

        sort($globPaths);

        $this->assertSame(['glob/alpha', 'glob/beta'], $globPaths);
    }

    private function findChildByPath(TocNode $root, string $path): ?TocNode
    {
        foreach ($root->getChildren() as $child) {
            if ($child->getPath() === $path) {
                return $child;
            }
        }

        return null;
    }
}
