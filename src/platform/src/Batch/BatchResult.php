<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Batch;

/**
 * Result of a single request within a completed batch job.
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class BatchResult
{
    private function __construct(
        private readonly string $id,
        private readonly bool $success,
        private readonly ?string $content = null,
        private readonly ?string $error = null,
        private readonly int $inputTokens = 0,
        private readonly int $outputTokens = 0,
    ) {
    }

    public static function success(string $id, ?string $content, int $inputTokens = 0, int $outputTokens = 0): self
    {
        return new self($id, true, content: $content, inputTokens: $inputTokens, outputTokens: $outputTokens);
    }

    public static function failure(string $id, string $error): self
    {
        return new self($id, false, error: $error);
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function getError(): ?string
    {
        return $this->error;
    }

    public function getInputTokens(): int
    {
        return $this->inputTokens;
    }

    public function getOutputTokens(): int
    {
        return $this->outputTokens;
    }
}
