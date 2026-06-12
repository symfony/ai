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

use App\Cookbook\Cookbook;
use App\Cookbook\Exception\CookbookArticleNotFoundException;
use App\Cookbook\Exception\CookbookGenerationException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class CookbookTest extends TestCase
{
    private const FIXTURE = __DIR__.'/../fixtures/cookbook.json';
    private const CONTENT = __DIR__.'/../fixtures/content';

    public function testGetAllArticlesReturnsArticlesInManifestOrder()
    {
        $this->assertSame(['first', 'second', 'third'], array_keys($this->cookbook()->getAllArticles()));
    }

    public function testGetPageReturnsTheArticle()
    {
        $page = $this->cookbook()->getPage('second');

        $this->assertSame('second', $page->article->slug);
        $this->assertSame('Second', $page->article->title);
        $this->assertSame(['Platform'], $page->article->components);
    }

    public function testGetPageThrowsForUnknownSlug()
    {
        $this->expectException(CookbookArticleNotFoundException::class);

        $this->cookbook()->getPage('missing');
    }

    public function testGetPageCarriesNeighborsInTheMiddle()
    {
        $page = $this->cookbook()->getPage('second');

        $this->assertNotNull($page->previous);
        $this->assertNotNull($page->next);
        $this->assertSame('first', $page->previous->slug);
        $this->assertSame('third', $page->next->slug);
    }

    public function testGetPageCarriesNeighborsAtBoundaries()
    {
        $first = $this->cookbook()->getPage('first');
        $this->assertNull($first->previous);
        $this->assertSame('second', $first->next->slug);

        $last = $this->cookbook()->getPage('third');
        $this->assertSame('second', $last->previous->slug);
        $this->assertNull($last->next);
    }

    public function testGetPageIncludesTheRenderedBody()
    {
        $this->assertStringContainsString('Second body.', $this->cookbook()->getPage('second')->body);
    }

    public function testGetPageThrowsWhenFragmentMissing()
    {
        $manifest = $this->manifestWith('ghost');

        try {
            $this->expectException(CookbookGenerationException::class);
            $this->cookbook($manifest)->getPage('ghost');
        } finally {
            @unlink($manifest);
        }
    }

    /**
     * Even if a manipulated slug somehow reached the manifest, the body lookup must
     * refuse to traverse out of the content directory.
     */
    #[DataProvider('maliciousSlugs')]
    public function testGetPageRejectsManipulatedSlugInManifest(string $slug)
    {
        $manifest = $this->manifestWith($slug);

        try {
            $this->expectException(CookbookGenerationException::class);
            $this->cookbook($manifest)->getPage($slug);
        } finally {
            @unlink($manifest);
        }
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function maliciousSlugs(): iterable
    {
        yield 'parent traversal' => ['../first'];
        yield 'deep traversal' => ['../../config/cookbook'];
        yield 'absolute path' => ['/etc/passwd'];
        yield 'dot segment' => ['first/../first'];
        yield 'null byte' => ["first\0"];
        yield 'uppercase' => ['First'];
    }

    public function testMissingManifestThrows()
    {
        $this->expectException(CookbookGenerationException::class);

        $this->cookbook('/does/not/exist/cookbook.json')->getAllArticles();
    }

    public function testInvalidManifestThrows()
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cookbook');
        file_put_contents($path, 'not json');

        try {
            $this->expectException(CookbookGenerationException::class);
            $this->cookbook($path)->getAllArticles();
        } finally {
            @unlink($path);
        }
    }

    private function cookbook(string $manifest = self::FIXTURE): Cookbook
    {
        return new Cookbook($manifest, self::CONTENT);
    }

    private function manifestWith(string $slug): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'cookbook');
        file_put_contents($path, (string) json_encode([
            ['slug' => $slug, 'title' => 'x', 'description' => 'x', 'icon' => 'x', 'components' => []],
        ]));

        return $path;
    }
}
