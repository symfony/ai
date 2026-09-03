<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi;

use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Exposes the gateway bookkeeping every universal-ai response carries.
 *
 * "cost" and "provider" are required root fields of every universal-ai response. They
 * matter more here than on a single-vendor bridge: the provider that answered is only
 * known at runtime when "fallbacks" is used, and the price varies per provider, so
 * dropping them makes spend impossible to attribute.
 *
 * The converters returning an ObjectResult pass both into their result object instead,
 * where they are part of the documented payload. This trait serves the converters whose
 * result is a plain binary or text payload with nowhere else to put them.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
trait ResultMetadataTrait
{
    /**
     * @param array<string, mixed> $data  the decoded universal-ai response
     * @param array<string, mixed> $extra additional metadata to expose alongside the
     *                                    gateway bookkeeping
     */
    private function attachEdenAiMetadata(ResultInterface $result, array $data, array $extra = []): void
    {
        $metadata = array_filter([
            ...$extra,
            'provider' => $data['provider'] ?? null,
            'cost' => isset($data['cost']) ? (float) $data['cost'] : null,
        ], static fn (mixed $value): bool => null !== $value);

        if ([] === $metadata) {
            return;
        }

        $result->getMetadata()->set($metadata);
    }
}
