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
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter implements ResultConverterInterface
{
    public function supports(Model $model): bool
    {
        return $model instanceof Perplexity;
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result));
        }

        $data = $result->getData();

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        $result = 1 === \count($choices) ? $choices[0] : new ChoiceResult(...$choices);

        return $result;
    }

    private function convertStream(RawResultInterface $result): \Generator
    {
        $searchResults = $citations = [];
        /** @var Metadata $metadata */
        $metadata = yield;

        foreach ($result->getDataStream() as $data) {
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
