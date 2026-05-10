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
use Symfony\AI\Mate\Bridge\Knowledge\Service\ChunkBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ChunkBuilderTest extends TestCase
{
    private string $docsDir;

    protected function setUp(): void
    {
        $this->docsDir = \dirname(__DIR__).'/Fixtures/docs';
    }

    public function testBuildProducesChunksForEveryPageInTheToc()
    {
        $toc = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);
        $builder = new ChunkBuilder();

        $chunks = $builder->build($toc, $this->docsDir);

        $paths = array_unique(array_map(static fn ($chunk) => $chunk->getPath(), $chunks));
        sort($paths);

        $this->assertSame(['', 'advanced', 'advanced/caching', 'setup'], $paths);
    }

    public function testSetupPageIsSplitIntoSections()
    {
        $toc = (new TocBuilder())->build($this->docsDir.'/index.rst', $this->docsDir);
        $chunks = (new ChunkBuilder())->build($toc, $this->docsDir);

        $setupChunks = array_values(array_filter(
            $chunks,
            static fn ($chunk) => 'setup' === $chunk->getPath(),
        ));

        $sectionTitles = array_map(static fn ($chunk) => $chunk->getSectionTitle(), $setupChunks);

        $this->assertContains('Setup Guide', $sectionTitles);
        $this->assertContains('Installing', $sectionTitles);
        $this->assertContains('Configuration', $sectionTitles);
    }
}
