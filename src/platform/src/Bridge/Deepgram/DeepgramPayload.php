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
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class DeepgramPayload
{
    public function __construct(
        private readonly array|string $payload,
    ) {
    }

    public function asTextToSpeechPayload(): string
    {
        if (\is_string($this->payload)) {
            return $this->payload;
        }

        if (\is_array($this->payload) && !\array_key_exists('text', $this->payload)) {
            throw new InvalidArgumentException('');
        }

        return $this->payload['text'];
    }

    public function asSpeechToTextPayload(): string
    {
        if (\is_string($this->payload)) {
            return \sprintf('data:mp3;base64,%s', $this->payload);
        }

        if (\is_array($this->payload) && !\array_key_exists('data', $this->payload['input_audio'])) {
            throw new InvalidArgumentException('');
        }

        return \sprintf('data:%s;base64,%s', $this->payload['input_audio']['format'], $this->payload['input_audio']['data']);
    }
}
