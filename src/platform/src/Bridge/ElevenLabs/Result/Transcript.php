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

/**
 * Transcript of an ElevenLabs speech-to-text (/v1/speech-to-text) request.
 *
 * It carries the plain transcript text alongside the additional export formats
 * (e.g. SRT subtitles) that ElevenLabs returns when requested through the
 * `additional_formats` multipart field.
 *
 * @see https://elevenlabs.io/docs/api-reference/speech-to-text/convert
 *
 * @author Dmytro Khaperets <khaperets@gmail.com>
 */
final class Transcript
{
    /**
     * @param string                 $text              The plain transcript text
     * @param list<AdditionalFormat> $additionalFormats The additional export formats returned by ElevenLabs
     */
    public function __construct(
        private readonly string $text,
        private readonly array $additionalFormats = [],
    ) {
    }

    public function getText(): string
    {
        return $this->text;
    }

    /**
     * @return list<AdditionalFormat>
     */
    public function getAdditionalFormats(): array
    {
        return $this->additionalFormats;
    }

    /**
     * Returns the decoded content of the first additional format whose
     * `requestedFormat` matches the given identifier (e.g. `srt`, `txt`, `html`).
     *
     * When ElevenLabs reports the content as base64-encoded, it is decoded
     * before being returned. `null` is returned when the format was not
     * requested; a {@see \Symfony\AI\Platform\Exception\RuntimeException} is
     * thrown when the base64 payload could not be decoded.
     */
    public function getAdditionalFormat(string $format): ?string
    {
        foreach ($this->additionalFormats as $additionalFormat) {
            if ($additionalFormat->getRequestedFormat() !== $format) {
                continue;
            }

            return $additionalFormat->getDecodedContent();
        }

        return null;
    }

    /**
     * Convenience accessor for the SubRip (SRT) subtitle content.
     */
    public function asSubRipText(): ?string
    {
        return $this->getAdditionalFormat('srt');
    }
}
