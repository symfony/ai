<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Tests\Capability;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\ReadTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\SearchTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\TocTool;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\ChunkBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KeywordSearcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Tests\Fixtures\FixtureProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ToolsTest extends TestCase
{
    private string $cacheDir;
    private ProviderRegistry $registry;
    private KnowledgeCache $cache;

    protected function setUp(): void
    {
        $this->cacheDir = sys_get_temp_dir().'/ai_mate_knowledge_tools_'.uniqid();
        mkdir($this->cacheDir, 0755, true);

        $provider = new FixtureProvider(\dirname(__DIR__).'/Fixtures/docs');
        $this->registry = new ProviderRegistry([$provider]);
        $this->cache = new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder());
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->cacheDir);
    }

    public function testTocToolWithoutProviderListsRegisteredProviders()
    {
        $tool = new TocTool($this->registry, $this->cache);

        $output = ResponseEncoder::decode($tool->browse());

        $this->assertCount(1, $output['providers']);
        $this->assertSame('fixture', $output['providers'][0]['name']);
        $this->assertSame('rst', $output['providers'][0]['format']);
        $this->assertArrayHasKey('usage', $output);
    }

    public function testTocToolReturnsRootChildrenWhenNoPathGiven()
    {
        $tool = new TocTool($this->registry, $this->cache);

        $output = ResponseEncoder::decode($tool->browse('fixture'));

        $this->assertSame('fixture', $output['provider']);
        $this->assertSame('Sample Documentation', $output['title']);
        $this->assertCount(2, $output['children']);
    }

    public function testTocToolDrillsIntoChildPath()
    {
        $tool = new TocTool($this->registry, $this->cache);

        $output = ResponseEncoder::decode($tool->browse('fixture', 'advanced'));

        $this->assertSame('advanced', $output['path']);
        $this->assertCount(1, $output['children']);
        $this->assertSame('advanced/caching', $output['children'][0]['path']);
    }

    public function testReadToolReturnsSectionsForPage()
    {
        $tool = new ReadTool($this->registry, $this->cache);

        $output = ResponseEncoder::decode($tool->read('fixture', 'setup'));

        $this->assertSame('setup', $output['path']);
        $this->assertNotEmpty($output['sections']);

        $titles = array_map(static fn (array $section) => $section['title'], $output['sections']);
        $this->assertContains('Installing', $titles);
        $this->assertContains('Configuration', $titles);
    }

    public function testSearchToolFindsMatchingChunks()
    {
        $tool = new SearchTool($this->registry, $this->cache, new KeywordSearcher());

        $output = ResponseEncoder::decode($tool->search('fixture', 'FrameworkBundle'));

        $this->assertSame('FrameworkBundle', $output['query']);
        $this->assertNotEmpty($output['results']);
        $this->assertSame('setup', $output['results'][0]['path']);
    }

    private function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);
        if (false === $items) {
            return;
        }

        foreach ($items as $item) {
            if ('.' === $item || '..' === $item) {
                continue;
            }

            $path = $directory.\DIRECTORY_SEPARATOR.$item;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($directory);
    }
}
