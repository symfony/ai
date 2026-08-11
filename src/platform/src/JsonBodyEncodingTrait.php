<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform;

/**
 * Encodes JSON request bodies, replacing malformed UTF-8 sequences.
 *
 * HttpClient's "json" option aborts the whole request when the payload
 * contains malformed UTF-8, which is common when tool results carry raw
 * process output. Encoding with JSON_INVALID_UTF8_SUBSTITUTE replaces
 * invalid sequences with U+FFFD so the request can still be sent; the
 * other flags mirror what the "json" option would have used.
 *
 * JSON_UNESCAPED_SLASHES is added on top: the "json" option escapes forward
 * slashes ("qwen/qwen3" becomes "qwen\/qwen3"), which several OpenAI-compatible
 * backends (vLLM, LM Studio) fail to match when they do a raw string comparison
 * on namespaced model names, returning a "model not found" error.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
trait JsonBodyEncodingTrait
{
    /**
     * @param array<int|string, mixed> $payload
     */
    private function encodeJsonBody(array $payload): string
    {
        return json_encode($payload, \JSON_HEX_TAG | \JSON_HEX_APOS | \JSON_HEX_AMP | \JSON_HEX_QUOT | \JSON_PRESERVE_ZERO_FRACTION | \JSON_INVALID_UTF8_SUBSTITUTE | \JSON_UNESCAPED_SLASHES | \JSON_THROW_ON_ERROR);
    }
}
