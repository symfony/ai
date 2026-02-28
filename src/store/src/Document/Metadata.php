<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Store\Document;

/**
 * @template-extends \ArrayObject<string, mixed>
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Metadata extends \ArrayObject
{
    public const KEY_PARENT_ID = '_parent_id';
    public const KEY_TEXT = '_text';
    public const KEY_SOURCE = '_source';
    public const KEY_SUMMARY = '_summary';

    public function hasParentId(): bool
    {
        return $this->offsetExists(self::KEY_PARENT_ID);
    }

    public function getParentId(): int|string|null
    {
        return $this->offsetExists(self::KEY_PARENT_ID)
            ? $this->offsetGet(self::KEY_PARENT_ID)
            : null;
    }

    public function setParentId(int|string $parentId): void
    {
        $this->offsetSet(self::KEY_PARENT_ID, $parentId);
    }

    public function hasText(): bool
    {
        return $this->offsetExists(self::KEY_TEXT);
    }

    public function setText(string $text): void
    {
        $this->offsetSet(self::KEY_TEXT, $text);
    }

    public function getText(): ?string
    {
        return $this->offsetExists(self::KEY_TEXT)
            ? $this->offsetGet(self::KEY_TEXT)
            : null;
    }

    public function hasSource(): bool
    {
        return $this->offsetExists(self::KEY_SOURCE);
    }

    public function getSource(): ?string
    {
        return $this->offsetExists(self::KEY_SOURCE)
            ? $this->offsetGet(self::KEY_SOURCE)
            : null;
    }

    public function setSource(string $source): void
    {
        $this->offsetSet(self::KEY_SOURCE, $source);
    }

    public function hasSummary(): bool
    {
        return $this->offsetExists(self::KEY_SUMMARY);
    }

    public function getSummary(): ?string
    {
        return $this->offsetExists(self::KEY_SUMMARY)
            ? $this->offsetGet(self::KEY_SUMMARY)
            : null;
    }

    public function setSummary(string $summary): void
    {
        $this->offsetSet(self::KEY_SUMMARY, $summary);
    }
}
