<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Venice;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PayloadInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class VenicePayload implements PayloadInterface
{
    /**
     * @param array<int|string, mixed>|string $payload
     */
    public function __construct(
        private readonly array|string $payload,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return array<string, mixed>
     */
    public function asVideoGenerationPayload(Model $model, array $options): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array for video generation.');
        }

        return match (true) {
            $model->supports(Capability::TEXT_TO_VIDEO) => [
                ...$options,
                'prompt' => $this->payload['text'] ?? $this->payload['prompt'] ?? throw new InvalidArgumentException('A valid input or a prompt is required for video generation.'),
            ],
            $model->supports(Capability::IMAGE_TO_VIDEO) => [
                ...$options,
                'prompt' => $this->payload['text'] ?? $this->payload['prompt'] ?? throw new InvalidArgumentException('A valid input or a prompt is required for video generation.'),
                'image_url' => $this->payload['image_url'] ?? throw new InvalidArgumentException('The image must be a valid URL or a data URL (ex: "data:").'),
            ],
            default => throw new InvalidArgumentException('Unsupported video generation.'),
        };
    }

    /**
     * @return non-empty-list<mixed>
     */
    public function asCompletionPayload(): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('Payload must be an array for completion.');
        }

        if (!\array_key_exists('messages', $this->payload)) {
            throw new InvalidArgumentException('Payload must contain "messages" key for completion.');
        }

        $messages = $this->payload['messages'];

        if (!\is_array($messages) || [] === $messages) {
            throw new InvalidArgumentException('Messages must be a non-empty array.');
        }

        return array_values($messages);
    }

    public function asImageGeneration(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The prompt cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('prompt', $this->payload)) {
            throw new InvalidArgumentException('The "prompt" key is missing.');
        }

        $prompt = $this->payload['prompt'];

        if (!\is_string($prompt) || '' === $prompt) {
            throw new InvalidArgumentException('The "prompt" key must be a non-empty string.');
        }

        return $prompt;
    }

    public function asTextToSpeechPayload(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The text cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('The "text" key is missing.');
        }

        $text = $this->payload['text'];

        if (!\is_string($text) || '' === $text) {
            throw new InvalidArgumentException('The "text" key must be a non-empty string.');
        }

        return $text;
    }

    public function asSpeechToTextPayload(): string
    {
        if (!\is_array($this->payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array when using file-based transcription endpoint, given "%s".', \gettype($this->payload)));
        }

        if (!\is_array($this->payload['input_audio'] ?? null)) {
            throw new InvalidArgumentException('Payload must contain an "input_audio" array key for transcription.');
        }

        if (!\is_string($this->payload['input_audio']['path'] ?? null)) {
            throw new InvalidArgumentException('Payload "input_audio" must contain a "path" string key for transcription.');
        }

        return $this->payload['input_audio']['path'];
    }

    public function asEmbeddingsPayload(): string
    {
        if (\is_string($this->payload)) {
            if ('' === $this->payload) {
                throw new InvalidArgumentException('The text cannot be empty.');
            }

            return $this->payload;
        }

        if (!\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('The "text" key is missing.');
        }

        $text = $this->payload['text'];

        if (!\is_string($text) || '' === $text) {
            throw new InvalidArgumentException('The "text" key must be a non-empty string.');
        }

        return $text;
    }
}
