<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Mock;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Pass-through converter for {@see MockModelClient}: returns the scripted {@see ResultInterface}
 * verbatim, so any result type is supported without per-type conversion code.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MockResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $payload = $result->getObject();

        if ($payload instanceof ResultInterface) {
            return $payload;
        }

        return new TextResult((string) ($result->getData()['text'] ?? ''));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
