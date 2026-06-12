<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests\Cookbook;

use App\Cookbook\Builder;
use App\Cookbook\Exception\CookbookGenerationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BuilderTest extends TestCase
{
    #[DataProvider('hrefs')]
    public function testRewriteHref(string $href, ?string $expected)
    {
        $this->assertSame($expected, $this->invoke('rewriteHref', $href));
    }

    /**
     * @return iterable<string, array{string, string|null}>
     */
    public static function hrefs(): iterable
    {
        yield 'absolute http is left untouched' => ['https://example.com/x', null];
        yield 'mailto is left untouched' => ['mailto:foo@example.com', null];
        yield 'in-page anchor is left untouched' => ['#section', null];
        yield 'non-html link is left untouched' => ['../images/diagram.svg', null];
        yield 'sibling cookbook page maps to the route' => ['rag-implementation.html', '/cookbook/rag-implementation'];
        yield 'sibling page keeps its anchor' => ['rag-implementation.html#step-1', '/cookbook/rag-implementation#step-1'];
        yield 'other doc page maps to symfony.com' => ['../components/agent.html', 'https://symfony.com/doc/current/ai/components/agent.html'];
        yield 'other doc page keeps its anchor' => ['../bundles/ai-bundle.html#config', 'https://symfony.com/doc/current/ai/bundles/ai-bundle.html#config'];
    }

    public function testReadToctreeOrder()
    {
        $index = $this->tempFile(<<<'RST'
            Cookbook
            ========

            .. toctree::
                :maxdepth: 1

                alpha
                beta
                gamma

            Other Section
            -------------

            * :doc:`alpha` - not part of the toctree
            RST);

        try {
            $this->assertSame(['alpha', 'beta', 'gamma'], $this->invoke('readToctreeOrder', $index));
        } finally {
            @unlink($index);
        }
    }

    public function testReadToctreeOrderReturnsEmptyForMissingFile()
    {
        $this->assertSame([], $this->invoke('readToctreeOrder', '/does/not/exist/index.rst'));
    }

    public function testParseFrontMatter()
    {
        $rst = $this->tempFile(<<<'RST'
            .. card:
                title: My Title
                description: A description, with a comma
                icon: tool
                components: Platform, Agent, Store

            My Title
            ========

            Body.
            RST);

        try {
            $this->assertSame([
                'title' => 'My Title',
                'description' => 'A description, with a comma',
                'icon' => 'tool',
                'components' => ['Platform', 'Agent', 'Store'],
            ], $this->invoke('parseFrontMatter', $rst));
        } finally {
            @unlink($rst);
        }
    }

    public function testParseFrontMatterThrowsOnMissingField()
    {
        $rst = $this->tempFile(<<<'RST'
            .. card:
                title: My Title
                description: A description
                components: Platform

            My Title
            ========
            RST);

        try {
            $this->expectException(CookbookGenerationException::class);
            $this->invoke('parseFrontMatter', $rst);
        } finally {
            @unlink($rst);
        }
    }

    public function testParseFrontMatterThrowsOnMissingFile()
    {
        $this->expectException(CookbookGenerationException::class);

        $this->invoke('parseFrontMatter', '/does/not/exist.rst');
    }

    public function testExtractArticleBody()
    {
        $html = <<<'HTML'
            <!DOCTYPE html>
            <html><body>
            <div itemprop="articleBody">
            <div class="section">
            <h1 id="t">Title<a class="headerlink" href="#t">¶</a></h1>
            <p>Intro paragraph.</p>
            <a href="streaming-responses.html">Sibling</a>
            <a href="../components/agent.html">Agent</a>
            <a href="https://example.com">External</a>
            </div>
            </div>
            </body></html>
            HTML;

        $fragment = $this->invoke('extractArticleBody', $html);

        $this->assertStringStartsWith('<!-- This file is generated', $fragment);
        $this->assertStringNotContainsString('<h1', $fragment);
        $this->assertStringNotContainsString('headerlink', $fragment);
        $this->assertStringContainsString('<p>Intro paragraph.</p>', $fragment);
        $this->assertStringContainsString('href="/cookbook/streaming-responses"', $fragment);
        $this->assertStringContainsString('href="https://symfony.com/doc/current/ai/components/agent.html"', $fragment);
        $this->assertStringContainsString('href="https://example.com"', $fragment);
    }

    public function testExtractArticleBodyThrowsWhenBodyMissing()
    {
        $this->expectException(CookbookGenerationException::class);

        $this->invoke('extractArticleBody', '<html><body><p>No article body marker.</p></body></html>');
    }

    private function invoke(string $method, mixed ...$args): mixed
    {
        $builder = new Builder('/unused/project', '/unused/manifest.json');

        return (new \ReflectionMethod(Builder::class, $method))->invoke($builder, ...$args);
    }

    private function tempFile(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cookbook_builder_');
        file_put_contents($path, $content);

        return $path;
    }
}
