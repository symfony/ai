<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface as PlatformResponseConverter;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements PlatformResponseConverter
{
    public function supports(Model $model): bool
    {
        return $model instanceof Perplexity;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result->getObject()));
        }

        $data = $result->getData();

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        $result = 1 === \count($choices) ? $choices[0] : new ChoiceResult(...$choices);

        return $result;
    }

    private function convertStream(HttpResponse $result): \Generator
    {
        $searchResults = $citations = [];
        /** @var Metadata $metadata */
        $metadata = yield;

        foreach ((new EventSourceHttpClient())->stream($result) as $chunk) {
            if (!$chunk instanceof ServerSentEvent || '[DONE]' === $chunk->getData()) {
                continue;
            }

            try {
                $data = $chunk->getArrayData();
            } catch (JsonException) {
                // try catch only needed for Symfony 6.4
                continue;
            }

            if (isset($data['choices'][0]['delta']['content'])) {
                yield $data['choices'][0]['delta']['content'];
            }

            if (isset($data['search_results'])) {
                $searchResults = $data['search_results'];
            }

            if (isset($data['citations'])) {
                $citations = $data['citations'];
            }
        }

        $metadata->add('search_results', $searchResults);
        $metadata->add('citations', $citations);
    }

    /**
     * @param array{
     *     index: int,
     *     message: array{
     *         role: 'assistant',
     *         content: ?string
     *     },
     *     delta: array{
     *         role: 'assistant',
     *         content: string,
     *     },
     *     finish_reason: 'stop'|'length',
     * } $choice
     */
    private function convertChoice(array $choice): TextResult
    {
        if (!\in_array($choice['finish_reason'], ['stop', 'length'], true)) {
            throw new RuntimeException(\sprintf('Unsupported finish reason "%s".', $choice['finish_reason']));
        }

        return new TextResult($choice['message']['content']);
    }
}
