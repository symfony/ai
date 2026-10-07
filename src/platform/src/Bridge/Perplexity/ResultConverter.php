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

use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter as OpenResponsesResultConverter;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\StreamResult;

/**
 * @phpstan-type SearchResult array{id: int, url: string, title: string, snippet: string, date?: string, last_updated?: string, source?: string}
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ResultConverter extends OpenResponsesResultConverter
{
    use HttpStatusErrorHandlingTrait;

    /**
     * Output items the Agent API reports for its built-in tools, next to the Responses API ones.
     */
    private const BUILT_IN_TOOL_OUTPUT_TYPES = [
        'search_results',
        'fetch_url_results',
        'finance_results',
        'people_search_results',
        'sandbox_results',
        'tool_search_output',
    ];

    public function supports(Model $model): bool
    {
        return $model instanceof Perplexity;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($result instanceof RawHttpResult) {
            $response = $result->getObject();

            if (400 === $response->getStatusCode()) {
                $error = json_decode($response->getContent(false), true)['error'] ?? [];
                $message = $error['message'] ?? '';

                if ('too_many_prompt_tokens' === ($error['type'] ?? null) || str_contains(strtolower($message), 'too long')) {
                    throw new ExceedContextSizeException('' !== $message ? $message : 'Context size exceeded');
                }
            }

            $this->throwOnHttpError($response);
        }

        if (!($options['stream'] ?? false)) {
            return parent::convert($result, $options);
        }

        $searchResults = [];
        $events = $this->filterStreamEvents($result->getDataStream(), $this->getFunctionNames($options), $searchResults);

        /** @var StreamResult $stream */
        $stream = parent::convert(new InMemoryRawResult(dataStream: $events, object: $result->getObject()), $options);

        return new StreamResult($this->appendSearchResults($stream->getContent(), $searchResults));
    }

    public function convertData(array $data): ResultInterface
    {
        $searchResults = [];

        if (\is_array($data['output'] ?? null)) {
            $searchResults = self::collectSearchResults($data['output']);
            $data['output'] = array_values(array_filter(
                $data['output'],
                static fn (array $item): bool => !\in_array($item['type'] ?? null, self::BUILT_IN_TOOL_OUTPUT_TYPES, true),
            ));
        }

        $result = parent::convertData($data);

        if ([] !== $searchResults) {
            $result->getMetadata()->add('search_results', $searchResults);

            if (!$result->getMetadata()->has('citations')) {
                $result->getMetadata()->add('citations', array_column($searchResults, 'url'));
            }
        }

        return $result;
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }

    /**
     * Drops the built-in tool calls (e.g. "search_web"), announced as function calls that never complete.
     *
     * @param iterable<array<string, mixed>> $events
     * @param list<string>                   $functionNames
     * @param list<SearchResult>             $searchResults
     *
     * @return \Generator<array<string, mixed>>
     */
    private function filterStreamEvents(iterable $events, array $functionNames, array &$searchResults): \Generator
    {
        foreach ($events as $event) {
            $item = $event['item'] ?? null;

            if ('response.output_item.done' === ($event['type'] ?? null) && 'search_results' === ($item['type'] ?? null)) {
                $searchResults = [...$searchResults, ...self::collectSearchResults([$item])];
            }

            if ('function_call' === ($item['type'] ?? null) && !\in_array($item['name'] ?? null, $functionNames, true)) {
                continue;
            }

            yield $event;
        }
    }

    /**
     * @param list<SearchResult> $searchResults
     */
    private function appendSearchResults(\Generator $deltas, array &$searchResults): \Generator
    {
        yield from $deltas;

        if ([] !== $searchResults) {
            yield new MetadataDelta('search_results', $searchResults);
            yield new MetadataDelta('citations', array_column($searchResults, 'url'));
        }
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return list<string>
     */
    private function getFunctionNames(array $options): array
    {
        $names = [];

        foreach ($options['tools'] ?? [] as $tool) {
            if ('function' === ($tool['type'] ?? null) && isset($tool['name'])) {
                $names[] = $tool['name'];
            }
        }

        return $names;
    }

    /**
     * @param array<mixed> $output
     *
     * @return list<SearchResult>
     */
    private static function collectSearchResults(array $output): array
    {
        $searchResults = [];

        foreach ($output as $item) {
            if ('search_results' === ($item['type'] ?? null)) {
                $searchResults = [...$searchResults, ...($item['results'] ?? [])];
            }
        }

        return $searchResults;
    }
}
