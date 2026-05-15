<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Knowledge;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\ReadTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\SearchTool;
use Symfony\AI\Mate\Bridge\Knowledge\Capability\TocTool;
use Symfony\AI\Mate\Bridge\Knowledge\Provider\ProviderRegistry;
use Symfony\AI\Mate\Bridge\Knowledge\Service\ChunkBuilder;
use Symfony\AI\Mate\Bridge\Knowledge\Service\GitFetcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KeywordSearcher;
use Symfony\AI\Mate\Bridge\Knowledge\Service\KnowledgeCache;
use Symfony\AI\Mate\Bridge\Knowledge\Service\TocBuilder;
use Symfony\AI\Mate\Bridge\Symfony\Knowledge\SymfonyDocsProvider;
use Symfony\AI\Mate\Encoding\ResponseEncoder;
use Symfony\AI\Store\Document\Loader\RstLoader;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * End-to-end test that wires the Symfony bridge's {@see SymfonyDocsProvider}
 * through the Knowledge bridge's registry, cache and MCP tools.
 *
 * The "remote" Symfony docs repo is replaced with a local bare git repo that
 * contains a small RST fixture, so the test exercises the real `git clone`
 * code path without hitting the network.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SymfonyDocsProviderIntegrationTest extends TestCase
{
    private string $sandbox = '';
    private string $remoteRepo = '';
    private string $cacheDir = '';

    protected function setUp(): void
    {
        if (!class_exists(RstLoader::class)) {
            $this->markTestSkipped('symfony/ai-store version with RstLoader is required for this end-to-end test.');
        }

        if (null === (new ExecutableFinder())->find('git')) {
            $this->markTestSkipped('git binary is not available in PATH.');
        }

        $this->sandbox = sys_get_temp_dir().'/ai_mate_symfony_docs_e2e_'.uniqid();
        $this->remoteRepo = $this->sandbox.'/remote.git';
        $this->cacheDir = $this->sandbox.'/cache';

        mkdir($this->cacheDir, 0755, true);

        $this->setUpRemoteRepo();
    }

    protected function tearDown(): void
    {
        if ('' !== $this->sandbox) {
            $this->removeDirectory($this->sandbox);
        }
    }

    public function testKnowledgeToolsServeSymfonyProviderEndToEnd()
    {
        $provider = new SymfonyDocsProvider(new GitFetcher(), 'file://'.$this->remoteRepo, 'main');
        $registry = new ProviderRegistry([$provider]);
        $cache = new KnowledgeCache($this->cacheDir, new TocBuilder(), new ChunkBuilder());

        // 1. Listing providers without arguments
        $tocTool = new TocTool($registry, $cache);
        $listed = ResponseEncoder::decode($tocTool->browse());
        $this->assertSame('symfony', $listed['providers'][0]['name']);

        // 2. Browsing the Symfony provider triggers the clone + index
        $browsed = ResponseEncoder::decode($tocTool->browse('symfony'));
        $this->assertSame('symfony', $browsed['provider']);
        $this->assertSame('Symfony Docs (Fake)', $browsed['title']);
        $childPaths = array_map(static fn (array $c): string => $c['path'], $browsed['children']);
        $this->assertContains('setup', $childPaths);

        // 3. Reading a page returns ordered sections
        $read = ResponseEncoder::decode((new ReadTool($registry, $cache))->read('symfony', 'setup'));
        $this->assertSame('Setup', $read['title']);
        $sectionTitles = array_map(static fn (array $s): string => $s['title'], $read['sections']);
        $this->assertContains('Installation', $sectionTitles);

        // 4. Searching across chunks
        $search = ResponseEncoder::decode((new SearchTool($registry, $cache, new KeywordSearcher()))->search('symfony', 'FrameworkBundle'));
        $this->assertSame('FrameworkBundle', $search['query']);
        $this->assertNotEmpty($search['results']);
        $this->assertSame('setup', $search['results'][0]['path']);

        // 5. Cache artifacts including metadata.json are present
        $this->assertFileExists($this->cacheDir.'/symfony/toc.json');
        $this->assertFileExists($this->cacheDir.'/symfony/chunks.json');
        $this->assertFileExists($this->cacheDir.'/symfony/metadata.json');
        $metadata = json_decode((string) file_get_contents($this->cacheDir.'/symfony/metadata.json'), true);
        $this->assertSame('symfony', $metadata['provider']);
        $this->assertGreaterThan(0, $metadata['chunk_count']);
    }

    private function setUpRemoteRepo(): void
    {
        $working = $this->sandbox.'/working';
        mkdir($working, 0755, true);
        mkdir($working.'/setup', 0755, true);

        file_put_contents($working.'/index.rst', <<<RST
            Symfony Docs (Fake)
            ===================

            .. toctree::
                :maxdepth: 2

                setup/index

            RST);

        file_put_contents($working.'/setup/index.rst', <<<RST
            Setup
            =====

            Installation
            ------------

            Run ``composer require symfony/framework-bundle`` to install the FrameworkBundle.

            Configuration
            -------------

            Edit ``config/packages/framework.yaml`` to configure the FrameworkBundle.

            RST);

        $this->git(['init', '--initial-branch=main', '--quiet'], $working);
        $this->git(['config', 'user.email', 'test@example.com'], $working);
        $this->git(['config', 'user.name', 'Test'], $working);
        $this->git(['add', '.'], $working);
        $this->git(['commit', '-m', 'init', '--quiet'], $working);

        // Convert the working repo into a bare repo that SymfonyDocsProvider can clone.
        mkdir($this->remoteRepo, 0755, true);
        $this->git(['clone', '--bare', '--quiet', $working, $this->remoteRepo], $this->sandbox);
    }

    /**
     * @param list<string> $command
     */
    private function git(array $command, string $cwd): void
    {
        $process = new Process(array_merge(['git'], $command), $cwd, null, null, 60);
        $process->mustRun();
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
            if (is_dir($path) && !is_link($path)) {
                $this->removeDirectory($path);
            } else {
                @unlink($path);
            }
        }

        rmdir($directory);
    }
}
