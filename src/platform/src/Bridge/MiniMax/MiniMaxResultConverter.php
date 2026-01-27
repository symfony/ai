<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class MiniMaxResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof MiniMax;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        return match (true) {
            \array_key_exists('object', $data) && 'chat.completion' === $data['object'] => new TextResult($data['choices'][0]['message']['content']),
            !\array_key_exists('object', $data) && [] !== array_filter($data, static fn (array $chunk): bool => 'chat.completion.chunk' === $chunk['object']) => new StreamResult((static function () use ($result): \Generator {
                foreach ($result->getDataStream() as $data) {
                    yield new MiniMaxTextChunk($data);
                }
            })()),
            \array_key_exists('data', $data) && \array_key_exists('audio', $data['data']) => new BinaryResult($data['data']['audio']),
            \array_key_exists('data', $data) && \array_key_exists('image_base64', $data['data']) => new ChoiceResult(...array_map(static fn (string $resource): BinaryResult => new BinaryResult($resource), $data['data']['image_base64'])),
            \array_key_exists('data', $data) && \array_key_exists('image_urls', $data['data']) => new ChoiceResult(...array_map(static fn (string $url): TextResult => new TextResult($url), $data['data']['image_urls'])),
            default => throw new InvalidArgumentException(),
        };
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return new TokenUsageExtractor();
    }
}
