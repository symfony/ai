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
 * A single section/chunk of a documentation page.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class PageChunk
{
    public function __construct(
        private string $path,
        private string $pageTitle,
        private string $sectionTitle,
        private int $depth,
        private string $content,
    ) {
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getPageTitle(): string
    {
        return $this->pageTitle;
    }

    public function getSectionTitle(): string
    {
        return $this->sectionTitle;
    }

    public function getDepth(): int
    {
        return $this->depth;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return array{path: string, page_title: string, section_title: string, depth: int, content: string}
     */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'page_title' => $this->pageTitle,
            'section_title' => $this->sectionTitle,
            'depth' => $this->depth,
            'content' => $this->content,
        ];
    }

    /**
     * @param array{path: string, page_title: string, section_title: string, depth: int, content: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['path'],
            $data['page_title'],
            $data['section_title'],
            $data['depth'],
            $data['content'],
        );
    }
}
