<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Result;

use Symfony\AI\Platform\Exception\RuntimeException;

/**
 * A single additional export format returned by the ElevenLabs speech-to-text
 * endpoint alongside the plain transcript (e.g. SRT subtitles, plain text, HTML).
 *
 * Regardless of the requested format, ElevenLabs always reports the same set of
 * metadata: the identifier of the format that was requested, its file extension,
 * the related content type, whether the content is base64-encoded and the
 * content payload itself.
 *
 * @see https://elevenlabs.io/docs/api-reference/speech-to-text/convert
 *
 * @author Dmytro Khaperets <khaperets@gmail.com>
 */
final class AdditionalFormat
{
    /**
     * @param string $requestedFormat Identifier of the format that was requested (e.g. `srt`, `txt`, `html`)
     * @param string $fileExtension   File extension associated with the format, including the leading dot (e.g. `.srt`)
     * @param string $contentType     MIME type associated with the format (e.g. `text/plain`)
     * @param bool   $isBase64Encoded Whether the {@see $content} payload is base64-encoded
     * @param string $content         The raw content payload, base64-encoded when {@see $isBase64Encoded} is true
     */
    public function __construct(
        private readonly string $requestedFormat,
        private readonly string $fileExtension,
        private readonly string $contentType,
        private readonly bool $isBase64Encoded,
        private readonly string $content,
    ) {
    }

    /**
     * Builds an instance from the raw payload returned by ElevenLabs.
     *
     * @param array<string, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            $data['requested_format'] ?? '',
            $data['file_extension'] ?? '',
            $data['content_type'] ?? '',
            $data['is_base64_encoded'] ?? false,
            $data['content'] ?? '',
        );
    }

    public function getRequestedFormat(): string
    {
        return $this->requestedFormat;
    }

    public function getFileExtension(): string
    {
        return $this->fileExtension;
    }

    public function getContentType(): string
    {
        return $this->contentType;
    }

    public function isBase64Encoded(): bool
    {
        return $this->isBase64Encoded;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * Returns the decoded content, decoding the base64 payload when ElevenLabs
     * reported it as base64-encoded.
     *
     * @throws RuntimeException when the base64 payload could not be decoded
     */
    public function getDecodedContent(): string
    {
        if (!$this->isBase64Encoded) {
            return $this->content;
        }

        $decoded = base64_decode($this->content, true);

        if (false === $decoded) {
            throw new RuntimeException(\sprintf('The base64-encoded content of the "%s" additional format could not be decoded.', $this->requestedFormat));
        }

        return $decoded;
    }
}
