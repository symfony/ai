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
 * Value object representing a code analysis request.
 *
 * This immutable object encapsulates all the information needed to perform
 * a comprehensive code analysis, ensuring type safety and clear contracts.
 *
 * @author Aria Vahidi <aria.vahidi2020@gmail.com>
 */
final readonly class AnalysisRequest
{
    /**
     * @param array<string>        $rules
     * @param array<string, mixed> $options
     * @param array<string, mixed> $context
     */
    public function __construct(
        public string $code,
        public AnalysisType $type,
        public string $depth = 'standard',
        public ?string $filePath = null,
        public ?string $projectRoot = null,
        public array $rules = [],
        public array $options = [],
        public array $context = [],
        public ?string $requestId = null,
    ) {
    }

    /**
     * Creates a new request with modified properties.
     *
     * @param array<string, mixed> $changes
     */
    public function withChanges(array $changes): self
    {
        return new self(
            code: $changes['code'] ?? $this->code,
            type: $changes['type'] ?? $this->type,
            depth: $changes['depth'] ?? $this->depth,
            filePath: $changes['filePath'] ?? $this->filePath,
            projectRoot: $changes['projectRoot'] ?? $this->projectRoot,
            rules: $changes['rules'] ?? $this->rules,
            options: $changes['options'] ?? $this->options,
            context: $changes['context'] ?? $this->context,
            requestId: $changes['requestId'] ?? $this->requestId,
        );
    }

    public function getUniqueKey(): string
    {
        return md5(
            $this->code.
            $this->type->value.
            $this->depth.
            ($this->filePath ?? '').
            serialize($this->rules).
            serialize($this->options)
        );
    }

    public function getCodeLength(): int
    {
        return \strlen($this->code);
    }

    public function getCodeComplexityEstimate(): float
    {
        $lines = explode("\n", $this->code);
        $complexityKeywords = ['if', 'else', 'while', 'for', 'foreach', 'switch', 'try', 'catch'];

        $complexity = 1; // Base complexity
        foreach ($complexityKeywords as $keyword) {
            $complexity += substr_count(strtolower($this->code), $keyword);
        }

        return min($complexity / \count($lines), 10); // Normalize to 0-10 scale
    }

    public function requiresHighPerformanceModel(): bool
    {
        return 'expert' === $this->depth
               || $this->getCodeLength() > 10000
               || $this->getCodeComplexityEstimate() > 7;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'code_length' => $this->getCodeLength(),
            'type' => $this->type->value,
            'depth' => $this->depth,
            'file_path' => $this->filePath,
            'project_root' => $this->projectRoot,
            'rules' => $this->rules,
            'options' => $this->options,
            'context' => $this->context,
            'request_id' => $this->requestId,
            'complexity_estimate' => $this->getCodeComplexityEstimate(),
            'unique_key' => $this->getUniqueKey(),
        ];
    }
}
