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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Deepgram;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $response = $result->getObject();

        dd($result->getData());

        return match (true) {
            str_contains($response->getInfo('url'), 'speak') => new BinaryResult($result->getObject()->getContent(), 'audio/mpeg'),
            str_contains($response->getInfo('url'), 'listen') => new TextResult($result->getData()),
            default => throw new InvalidArgumentException(),
        };
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
