<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Twig\Components;

use App\Cookbook\Article;
use App\Cookbook\Cookbook;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Renders the cookbook article grid with a component filter. The filter tags are
 * matched against the article components, so "MCP" also matches "MCP Bundle".
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[AsLiveComponent]
final class CookbookList
{
    use DefaultActionTrait;

    public const array TAGS = ['Platform', 'Agent', 'Store', 'MCP'];

    /**
     * @var list<string>
     */
    #[LiveProp]
    public array $activeTags = [];

    public function __construct(
        private readonly Cookbook $cookbook,
    ) {
    }

    /**
     * @return list<string>
     */
    public function getTags(): array
    {
        return self::TAGS;
    }

    public function isActive(string $tag): bool
    {
        return \in_array($tag, $this->activeTags, true);
    }

    /**
     * @return list<Article>
     */
    public function getArticles(): array
    {
        $articles = array_values($this->cookbook->getAllArticles());

        if ([] === $this->activeTags) {
            return $articles;
        }

        return array_values(array_filter($articles, $this->matches(...)));
    }

    #[LiveAction]
    public function toggleTag(#[LiveArg] string $tag): void
    {
        if (!\in_array($tag, self::TAGS, true)) {
            return;
        }

        if (\in_array($tag, $this->activeTags, true)) {
            $this->activeTags = array_values(array_filter($this->activeTags, static fn (string $active): bool => $active !== $tag));

            return;
        }

        $this->activeTags[] = $tag;
    }

    private function matches(Article $article): bool
    {
        foreach ($this->activeTags as $tag) {
            foreach ($article->components as $component) {
                if (str_contains(strtolower($component), strtolower($tag))) {
                    return true;
                }
            }
        }

        return false;
    }
}
