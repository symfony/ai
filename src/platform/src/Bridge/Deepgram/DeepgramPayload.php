<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Deepgram;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Wraps the input payload normalized by the Contract and exposes the shape
 * expected by Deepgram's REST and WebSocket APIs.
 *
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DeepgramPayload
{
    /**
     * @param array<int|string, mixed>|string $payload
     */
    public function __construct(
        private readonly array|string $payload,
    ) {
    }

    public function asTextToSpeechPayload(): string
    {
        if (\is_string($this->payload)) {
            return $this->payload;
        }

        if (!\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('The text-to-speech payload must contain a "text" key.');
        }

        if (!\is_string($this->payload['text'])) {
            throw new InvalidArgumentException('The "text" key of the text-to-speech payload must be a string.');
        }

        return $this->payload['text'];
    }

    public function isUrlBased(): bool
    {
        if (\is_string($this->payload)) {
            return false;
        }

        $audio = $this->payload['input_audio'] ?? null;

        return \is_array($audio) && \array_key_exists('url', $audio);
    }

    public function getAudioUrl(): string
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('The speech-to-text payload does not contain a remote URL.');
        }

        $audio = $this->payload['input_audio'] ?? null;
        if (!\is_array($audio) || !\array_key_exists('url', $audio)) {
            throw new InvalidArgumentException('The speech-to-text payload does not contain a remote URL.');
        }

        $url = $audio['url'];

        if (!\is_string($url) || '' === $url) {
            throw new InvalidArgumentException('The "url" entry of the speech-to-text payload must be a non-empty string.');
        }

        $scheme = parse_url($url, \PHP_URL_SCHEME);
        if ('http' !== $scheme && 'https' !== $scheme) {
            throw new InvalidArgumentException(\sprintf('The speech-to-text URL must use "http" or "https" scheme, "%s" given.', $scheme ?? 'none'));
        }

        return $url;
    }

    public function getAudioBinary(): string
    {
        $audio = $this->getInputAudio();

        if (!\array_key_exists('data', $audio) || !\is_string($audio['data']) || '' === $audio['data']) {
            throw new InvalidArgumentException('The speech-to-text payload must contain an "input_audio.data" base64-encoded string.');
        }

        $decoded = base64_decode($audio['data'], true);
        if (false === $decoded) {
            throw new InvalidArgumentException('The "input_audio.data" entry must be a valid base64-encoded string.');
        }

        return $decoded;
    }

    public function getAudioMimeType(): string
    {
        $audio = $this->getInputAudio();
        $format = $audio['format'] ?? null;

        return match ($format) {
            'mp3' => 'audio/mpeg',
            'wav' => 'audio/wav',
            'ogg' => 'audio/ogg',
            'flac' => 'audio/flac',
            'webm' => 'audio/webm',
            'aac' => 'audio/aac',
            'mulaw' => 'audio/x-mulaw',
            null, '' => 'application/octet-stream',
            default => \is_string($format) ? $format : 'application/octet-stream',
        };
    }

    /**
     * @return array<int|string, mixed>
     */
    private function getInputAudio(): array
    {
        if (\is_string($this->payload)) {
            throw new InvalidArgumentException('The speech-to-text payload must be a normalized audio array, raw string given.');
        }

        $audio = $this->payload['input_audio'] ?? null;
        if (!\is_array($audio)) {
            throw new InvalidArgumentException('The speech-to-text payload must contain an "input_audio" entry.');
        }

        return $audio;
    }
}
