<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Cookbook;

use App\Cookbook\Exception\CookbookGenerationException;
use Dom\HTMLDocument;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class Builder
{
    private const string DOCS_BASE_URL = 'https://symfony.com/doc/current/ai/';

    public function __construct(
        private string $projectDir,
        private string $manifestPath,
    ) {
    }

    /**
     * @return list<string>
     */
    public function generate(): array
    {
        $repoDir = \dirname($this->projectDir);
        $docsBuildDir = $repoDir.'/docs/_build';
        $docsCookbookDir = $repoDir.'/docs/cookbook';

        if (!is_file($docsBuildDir.'/vendor/bin/docs-builder')) {
            throw new CookbookGenerationException(\sprintf('docs-builder is not installed; run "composer install" in "%s".', $docsBuildDir));
        }

        $filesystem = new Filesystem();
        $outputDir = $docsBuildDir.'/output-cookbook';
        $this->buildDocs($docsBuildDir, $outputDir);

        $cookbookDir = $outputDir.'/cookbook';
        if (!is_dir($cookbookDir)) {
            throw new CookbookGenerationException(\sprintf('No cookbook output found in "%s".', $cookbookDir));
        }

        $targetDir = $this->projectDir.'/templates/cookbook/content';
        $filesystem->mkdir($targetDir);

        $generated = [];
        foreach (glob($cookbookDir.'/*.html') ?: [] as $file) {
            $slug = basename($file, '.html');
            if ('index' === $slug) {
                continue;
            }

            $fragment = $this->extractArticleBody((string) file_get_contents($file));
            $filesystem->dumpFile($targetDir.'/'.$slug.'.html', $fragment);
            $generated[$slug] = true;
        }

        $articles = $this->buildManifest($docsCookbookDir, array_keys($generated));
        $filesystem->dumpFile(
            $this->manifestPath,
            json_encode($articles, \JSON_PRETTY_PRINT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE)."\n",
        );

        $filesystem->remove($outputDir);

        return array_column($articles, 'slug');
    }

    /**
     * @param list<string> $generatedSlugs
     *
     * @return list<array{slug: string, title: string, description: string, icon: string, components: list<string>}>
     */
    private function buildManifest(string $docsCookbookDir, array $generatedSlugs): array
    {
        $remaining = array_fill_keys($generatedSlugs, true);

        $articles = [];
        foreach ($this->readToctreeOrder($docsCookbookDir.'/index.rst') as $slug) {
            if (!isset($remaining[$slug])) {
                continue;
            }

            $articles[] = ['slug' => $slug] + $this->parseFrontMatter($docsCookbookDir.'/'.$slug.'.rst');
            unset($remaining[$slug]);
        }

        foreach (array_keys($remaining) as $slug) {
            $articles[] = ['slug' => $slug] + $this->parseFrontMatter($docsCookbookDir.'/'.$slug.'.rst');
        }

        return $articles;
    }

    /**
     * @return list<string>
     */
    private function readToctreeOrder(string $indexPath): array
    {
        if (!is_file($indexPath)) {
            return [];
        }

        $order = [];
        $inToctree = false;
        foreach (file($indexPath, \FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_contains($line, '.. toctree::')) {
                $inToctree = true;
                continue;
            }

            if (!$inToctree) {
                continue;
            }

            if ('' === trim($line)) {
                continue;
            }

            if (!str_starts_with($line, ' ') && !str_starts_with($line, "\t")) {
                break;
            }

            $entry = trim($line);
            if (str_starts_with($entry, ':')) {
                continue;
            }

            $order[] = $entry;
        }

        return $order;
    }

    /**
     * @return array{title: string, description: string, icon: string, components: list<string>}
     */
    private function parseFrontMatter(string $rstPath): array
    {
        if (!is_file($rstPath)) {
            throw new CookbookGenerationException(\sprintf('Cookbook source "%s" not found.', $rstPath));
        }

        $meta = [];
        $inCard = false;
        foreach (file($rstPath, \FILE_IGNORE_NEW_LINES) ?: [] as $line) {
            if (str_starts_with($line, '.. card:')) {
                $inCard = true;
                continue;
            }

            if (!$inCard) {
                continue;
            }

            if ('' === trim($line) || (!str_starts_with($line, ' ') && !str_starts_with($line, "\t"))) {
                break;
            }

            if (1 === preg_match('/^\s*([a-z]+):\s*(.+)$/', $line, $matches)) {
                $meta[$matches[1]] = trim($matches[2]);
            }
        }

        foreach (['title', 'description', 'icon', 'components'] as $field) {
            if (!isset($meta[$field])) {
                throw new CookbookGenerationException(\sprintf('Cookbook front matter in "%s" is missing the "%s" field.', $rstPath, $field));
            }
        }

        return [
            'title' => $meta['title'],
            'description' => $meta['description'],
            'icon' => $meta['icon'],
            'components' => array_values(array_filter(array_map('trim', explode(',', $meta['components'])))),
        ];
    }

    private function buildDocs(string $docsBuildDir, string $outputDir): void
    {
        $process = new Process(
            ['vendor/bin/docs-builder', 'build:docs', '..', $outputDir, '--disable-cache', '--fail-on-errors'],
            $docsBuildDir,
            timeout: 300,
        );
        $process->run();

        if (!$process->isSuccessful()) {
            throw new CookbookGenerationException(\sprintf("docs-builder build failed:\n%s", $process->getErrorOutput().$process->getOutput()));
        }
    }

    private function extractArticleBody(string $html): string
    {
        $document = HTMLDocument::createFromString($html, \LIBXML_NOERROR);

        $body = $document->querySelector('[itemprop="articleBody"]');
        if (null === $body) {
            throw new CookbookGenerationException('Could not locate the article body in the generated HTML.');
        }

        foreach (iterator_to_array($body->querySelectorAll('a.headerlink')) as $headerlink) {
            $headerlink->remove();
        }

        // the page title is rendered by the layout from the article metadata
        $title = $body->querySelector('h1');
        if (null !== $title) {
            $title->remove();
        }

        foreach (iterator_to_array($body->querySelectorAll('a[href]')) as $link) {
            $rewritten = $this->rewriteHref($link->getAttribute('href'));
            if (null !== $rewritten) {
                $link->setAttribute('href', $rewritten);
            }
        }

        $fragment = '';
        foreach ($body->childNodes as $child) {
            $fragment .= $document->saveHtml($child);
        }

        return "<!-- This file is generated from docs/cookbook by \"bin/console app:cookbook:build\". Do not edit. -->\n".trim($fragment)."\n";
    }

    private function rewriteHref(string $href): ?string
    {
        if (str_starts_with($href, 'http://') || str_starts_with($href, 'https://') || str_starts_with($href, 'mailto:') || str_starts_with($href, '#')) {
            return null;
        }

        $fragment = '';
        if (str_contains($href, '#')) {
            [$href, $anchor] = explode('#', $href, 2);
            $fragment = '#'.$anchor;
        }

        if (!str_ends_with($href, '.html')) {
            return null;
        }

        if (str_starts_with($href, '../')) {
            return self::DOCS_BASE_URL.substr($href, 3).$fragment;
        }

        return '/cookbook/'.basename($href, '.html').$fragment;
    }
}
