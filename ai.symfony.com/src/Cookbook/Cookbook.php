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

use App\Cookbook\Exception\CookbookArticleNotFoundException;
use App\Cookbook\Exception\CookbookGenerationException;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Cookbook
{
    /**
     * @var array<string, Article>|null indexed by slug
     */
    private ?array $articles = null;

    public function __construct(
        private readonly string $manifestPath,
        private readonly string $contentDir,
    ) {
    }

    /**
     * @return array<string, Article>
     */
    public function getAllArticles(): array
    {
        if (null !== $this->articles) {
            return $this->articles;
        }

        if (!is_file($this->manifestPath)) {
            throw new CookbookGenerationException(\sprintf('Cookbook manifest "%s" is missing; run "bin/console app:cookbook:build".', $this->manifestPath));
        }

        $entries = json_decode((string) file_get_contents($this->manifestPath), true);
        if (!\is_array($entries)) {
            throw new CookbookGenerationException(\sprintf('Cookbook manifest "%s" is not valid JSON.', $this->manifestPath));
        }

        $articles = [];
        foreach ($entries as $entry) {
            $articles[$entry['slug']] = new Article(
                $entry['slug'],
                $entry['title'],
                $entry['description'],
                $entry['icon'],
                $entry['components'],
            );
        }

        return $this->articles = $articles;
    }

    public function getPage(string $slug): Page
    {
        $articles = $this->getAllArticles();

        if (!isset($articles[$slug])) {
            throw new CookbookArticleNotFoundException(\sprintf('Cookbook article "%s" not found.', $slug));
        }

        $slugs = array_keys($articles);
        $position = (int) array_search($slug, $slugs, true);

        $previousSlug = $slugs[$position - 1] ?? null;
        $nextSlug = $slugs[$position + 1] ?? null;

        return new Page(
            $articles[$slug],
            $this->body($slug),
            null !== $previousSlug ? $articles[$previousSlug] : null,
            null !== $nextSlug ? $articles[$nextSlug] : null,
        );
    }

    private function body(string $slug): string
    {
        if (1 !== preg_match('/^[a-z0-9-]+$/', $slug)) {
            throw new CookbookGenerationException(\sprintf('Invalid cookbook slug "%s".', $slug));
        }

        $path = $this->contentDir.'/'.$slug.'.html';
        if (!is_file($path)) {
            throw new CookbookGenerationException(\sprintf('Cookbook fragment "%s" is missing; run "bin/console app:cookbook:build".', $path));
        }

        return (string) file_get_contents($path);
    }
}
