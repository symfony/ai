<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Result\Stream\Delta;

/**
 * Carries non-user-facing stream metadata that should be promoted to result metadata and skipped from visible stream output.
 *
 * Use this for structured metadata that only becomes available during or at the end of a stream, such as citations,
 * grounding data, or search results.
 *
 * Do not use this as a generic escape hatch for provider-specific payloads. User-visible streamed content should use
 * semantic deltas like `TextDelta`, `ThinkingDelta`, `ToolCallComplete`, or `TokenUsage` instead.
 *
 * @author Fabien Potencier <fabien@symfony.com>
 */
final class MetadataDelta implements DeltaInterface
{
    public function __construct(
        private readonly string $key,
        private readonly mixed $value,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }
}
