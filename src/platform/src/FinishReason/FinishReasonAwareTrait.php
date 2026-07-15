<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\FinishReason;

use Symfony\AI\Platform\Result\ResultInterface;

/**
 * Attaches the provider finish reason to a buffered result.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
trait FinishReasonAwareTrait
{
    /**
     * Adds the `finish_reason` metadata, so a clean stop can be told apart from a truncation or a
     * content filter intervention after the fact.
     *
     * Providers omit the reason on some responses; the metadata is then left unset rather than guessed,
     * which is why a null reason is accepted here and every bridge mapper returns null for a missing value.
     *
     * @template T of ResultInterface
     *
     * @param T $result
     *
     * @return T
     */
    protected function withFinishReason(ResultInterface $result, ?FinishReason $finishReason): ResultInterface
    {
        if (null !== $finishReason) {
            $result->getMetadata()->add('finish_reason', $finishReason);
        }

        return $result;
    }
}
