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

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class OllamaPayload
{
    /**
     * @param array<int|string, mixed>|string $payload
     */
    public function __construct(
        private readonly array|string $payload,
    ) {
    }

    public function supportGeneration(): bool
    {
        return \is_string($this->payload) && '' !== $this->payload;
    }

    public function asGenerationPayload(): string
    {
        if (!\is_string($this->payload) || '' === $this->payload) {
            throw new InvalidArgumentException('The payload must be a non-empty string.');
        }

        return $this->payload;
    }

    public function supportCompletion(): bool
    {
        return \is_array($this->payload) && \array_key_exists('messages', $this->payload);
    }

    /**
     * @return array<int|string, mixed>
     */
    public function asCompletionPayload(): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('The payload must be an array with the "messages" key.');
        }

        if (\array_key_exists('text', $this->payload) && [] === $this->payload['messages']) {
            throw new InvalidArgumentException('The messages cannot be empty.');
        }

        return $this->payload;
    }

    /**
     * @return array<int|string, mixed>|string
     */
    public function asInitialPayload(): array|string
    {
        return $this->payload;
    }
}
