<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama;

/**
 * @author Shaun Johnston <shaun@snj.au>
 */
final class OllamaMessageChunk implements \Stringable
{
    /**
     * @param array<string, mixed> $message
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public readonly string $model,
        public readonly \DateTimeImmutable $created_at,
        public readonly array|string $message,
        public readonly bool $done,
        public readonly array $raw,
    ) {
    }

    public function __toString(): string
    {
        return \is_string($this->message) ? $this->message : $this->message['content'] ?? '';
    }

    public function getContent(): ?string
    {
        return \is_string($this->message) ? $this->message : $this->message['content'] ?? null;
    }

    public function getThinking(): ?string
    {
        return \is_string($this->message) ? $this->raw['thinking'] ?? null : $this->message['thinking'] ?? null;
    }

    public function getRole(): ?string
    {
        return \is_string($this->message) ? null : $this->message['role'] ?? null;
    }

    public function isDone(): bool
    {
        return $this->done;
    }
}
