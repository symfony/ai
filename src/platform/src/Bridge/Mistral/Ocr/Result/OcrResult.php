<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Ocr\Result;

/**
 * Structured result of a Mistral OCR (/v1/ocr) request.
 *
 * @author Tac Tacelosky <tacman@gmail.com>
 */
final class OcrResult
{
    /**
     * @param Page[]                    $pages
     * @param array<string, mixed>|null $usageInfo
     */
    public function __construct(
        private readonly array $pages,
        private readonly string $model,
        private readonly ?array $usageInfo = null,
    ) {
    }

    /**
     * @return Page[]
     */
    public function getPages(): array
    {
        return $this->pages;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getUsageInfo(): ?array
    {
        return $this->usageInfo;
    }

    /**
     * Returns the markdown of all pages concatenated, separated by a blank line.
     */
    public function getMarkdown(): string
    {
        return implode("\n\n", array_map(static fn (Page $page): string => $page->getMarkdown(), $this->pages));
    }
}
