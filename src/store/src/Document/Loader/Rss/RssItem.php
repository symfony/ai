<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document\Loader\Rss;

use Symfony\Component\Uid\Uuid;

/**
 * @author Niklas Grießer <niklas@griesser.me>
 */
final class RssItem
{
    public function __construct(
        private readonly Uuid $id,
        private readonly string $title,
        private readonly string $link,
        private readonly \DateTimeImmutable $date,
        private readonly string $description,
        private readonly ?string $author,
        private readonly ?string $content,
    ) {
    }

    public function toString(): string
    {
        return <<<EOD
            Title: {$this->title}
            Date: {$this->date->format('Y-m-d H:i')}
            Link: {$this->link}
            Description: {$this->description}

            {$this->content}
            EOD;
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     date: string,
     *     link: string,
     *     author: string,
     *     description: string,
     *     content: string,
     * }
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id->toRfc4122(),
            'title' => $this->title,
            'date' => $this->date->format('Y-m-d H:i'),
            'link' => $this->link,
            'author' => $this->author,
            'description' => $this->description,
            'content' => $this->content,
        ];
    }
}
