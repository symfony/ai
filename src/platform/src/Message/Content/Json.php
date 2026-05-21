<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\Content;

/**
 * Holds a structured payload that the application wants to keep typed in the message bag
 * but ship to the provider as a JSON-encoded text block.
 *
 * Typical use case: a structured-output response (e.g. response_format) is parsed into a
 * domain object and stored in an AssistantMessage. The application reads the object back
 * via getObject(); bridges serialize it as a JSON string when replaying the conversation.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Json implements ContentInterface
{
    /**
     * @param \JsonSerializable|\Stringable|object $object The wrapped value. Implementing \JsonSerializable controls
     *                                                     the JSON shape; objects implementing only \Stringable are
     *                                                     emitted via __toString() instead of being JSON-encoded.
     */
    public function __construct(
        private readonly object $object,
    ) {
    }

    public function getObject(): object
    {
        return $this->object;
    }

    /**
     * Encodes the wrapped value to a string suitable for inclusion in a provider payload.
     *
     * - \JsonSerializable: result of jsonSerialize() is JSON-encoded.
     * - \Stringable (without \JsonSerializable): cast via __toString().
     * - any other object: json_encode() falls back to public properties.
     */
    public function toJson(): string
    {
        if ($this->object instanceof \Stringable && !$this->object instanceof \JsonSerializable) {
            return (string) $this->object;
        }

        return json_encode($this->object, \JSON_THROW_ON_ERROR);
    }
}
