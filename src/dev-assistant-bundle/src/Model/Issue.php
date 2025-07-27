<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\DevAssistantBundle\Model;

/**
 * Represents a specific issue found during code analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class Issue
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public Severity $severity,
        public IssueCategory $category,
        public ?string $file = null,
        public ?int $line = null,
        public ?int $column = null,
        public ?string $rule = null,
        public ?string $fixSuggestion = null,
        public ?string $codeSnippet = null,
        public array $metadata = [],
    ) {
    }

    public function hasLocation(): bool
    {
        return null !== $this->file;
    }

    public function getLocation(): string
    {
        if (!$this->hasLocation()) {
            return 'Unknown location';
        }

        $location = $this->file;
        if (null !== $this->line) {
            $location .= ':' . $this->line;
            if (null !== $this->column) {
                $location .= ':' . $this->column;
            }
        }

        return $location;
    }

    public function isFixable(): bool
    {
        return null !== $this->fixSuggestion;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'severity' => $this->severity->value,
            'category' => $this->category->value,
            'file' => $this->file,
            'line' => $this->line,
            'column' => $this->column,
            'rule' => $this->rule,
            'fixSuggestion' => $this->fixSuggestion,
            'codeSnippet' => $this->codeSnippet,
            'location' => $this->getLocation(),
            'fixable' => $this->isFixable(),
            'metadata' => $this->metadata,
        ];
    }
}
