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
 * Represents an improvement suggestion from the analysis.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class Suggestion
{
    public function __construct(
        public string $id,
        public string $title,
        public string $description,
        public SuggestionType $type,
        public Priority $priority,
        public ?string $implementation = null,
        public ?string $reasoning = null,
        public ?string $exampleCode = null,
        public array $benefits = [],
        public ?float $estimatedImpact = null,
        public array $metadata = [],
    ) {
    }

    public function hasImplementation(): bool
    {
        return null !== $this->implementation;
    }

    public function hasExample(): bool
    {
        return null !== $this->exampleCode;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'type' => $this->type->value,
            'priority' => $this->priority->value,
            'implementation' => $this->implementation,
            'reasoning' => $this->reasoning,
            'exampleCode' => $this->exampleCode,
            'benefits' => $this->benefits,
            'estimatedImpact' => $this->estimatedImpact,
            'hasImplementation' => $this->hasImplementation(),
            'hasExample' => $this->hasExample(),
            'metadata' => $this->metadata,
        ];
    }
}
