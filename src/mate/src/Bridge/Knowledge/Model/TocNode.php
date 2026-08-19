<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Knowledge\Model;

/**
 * Node of the table-of-contents tree built from RST toctree directives.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TocNode
{
    /**
     * @param list<TocNode> $children
     */
    public function __construct(
        private string $path,
        private string $title,
        private bool $hasContent,
        private array $children = [],
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function hasContent(): bool
    {
        return $this->hasContent;
    }

    /**
     * @return list<TocNode>
     */
    public function getChildren(): array
    {
        return $this->children;
    }

    public function addChild(self $child): void
    {
        $this->children[] = $child;
    }

    public function findByPath(string $path): ?self
    {
        if ($this->path === $path) {
            return $this;
        }

        foreach ($this->children as $child) {
            $found = $child->findByPath($path);
            if (null !== $found) {
                return $found;
            }
        }

        return null;
    }

    /**
     * @return array{path: string, title: string, has_content: bool, children: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'title' => $this->title,
            'has_content' => $this->hasContent,
            'children' => array_map(static fn (self $child) => $child->toArray(), $this->children),
        ];
    }

    /**
     * @param array{path: string, title: string, has_content: bool, children: list<array<string, mixed>>} $data
     */
    public static function fromArray(array $data): self
    {
        $children = [];
        /** @var array{path: string, title: string, has_content: bool, children: list<array<string, mixed>>} $childData */
        foreach ($data['children'] as $childData) {
            $children[] = self::fromArray($childData);
        }

        return new self($data['path'], $data['title'], $data['has_content'], $children);
    }
}
