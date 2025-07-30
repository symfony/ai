<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Contract;

use Symfony\AI\Platform\Contract\ResultExtractor\ResultExtractorInterface;
use Symfony\AI\Platform\Contract\ResultExtractor\StreamResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\TextResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\ToolCallResultExtractor;
use Symfony\AI\Platform\Contract\ResultExtractor\VectorResultExtractor;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\Component\HttpClient\Chunk\ServerSentEvent;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Component\JsonPath\JsonCrawler;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;

/**
 * The ResultConverter is responsible for converting the json output of a model into structured result objects.
 * Is uses a set of ResultExtractors defining default JsonPath expressions to extract the relevant data.
 * With the `create()` method, you can overwrite the default extractors with different configuration of those instances.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class ResultConverter implements ResultConverterInterface
{
    /**
     * @param ResultExtractorInterface[] $resultExtractors
     */
    public function __construct(
        private iterable $resultExtractors,
        private StreamResultExtractor $streamExtractor,
    ) {
    }

    /**
     * Can be used to create a ResultConverter and provides a mechanism to overwrite the ResultExtractor with
     * instances that have different JsonPath expressions configured:
     *
     * For example:
     * ResultConverter::create([
     *     new VectorResultExtractor('$.embeddings[*].values'),
     * ])
     *
     * @param list<ResultExtractorInterface|StreamResultExtractor> $overwrites
     */
    public static function create(array $overwrites = []): self
    {
        $defaults = [
            VectorResultExtractor::class => new VectorResultExtractor(),
            ToolCallResultExtractor::class => new ToolCallResultExtractor(),
            TextResultExtractor::class => new TextResultExtractor(),
        ];
        $streamExtractor = new StreamResultExtractor();

        foreach ($overwrites as $overwrite) {
            if ($overwrite instanceof StreamResultExtractor) {
                $streamExtractor = $overwrite;
                continue;
            }

            if (!$overwrite instanceof ResultExtractorInterface) {
                throw new RuntimeException(\sprintf('Expected instance of "%s", got "%s"', ResultExtractorInterface::class, get_debug_type($overwrite)));
            }

            $defaults[$overwrite::class] = $overwrite;
        }

        return new self(array_values($defaults), $streamExtractor);
    }

    public function supports(Model $model): bool
    {
        return true;
    }

    public function convert(RawResultInterface|RawHttpResult $result, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($result->getObject()));
        }

        $crawler = new JsonCrawler($result->getObject()->getContent(false));

        $choices = [];
        foreach ($this->resultExtractors as $jsonPathConverter) {
            if (!$jsonPathConverter->supports($crawler)) {
                continue;
            }

            $choices += $jsonPathConverter->extract($crawler);
        }

        if ([] === $choices) {
            throw new RuntimeException('No suitable extractor found for the provided data.');
        }

        if (1 < \count($choices)) {
            return new ChoiceResult(...$choices);
        }

        return reset($choices);
    }

    private function convertStream(HttpResponse $result): \Generator
    {
        foreach ((new EventSourceHttpClient())->stream($result) as $chunk) {
            if (!$chunk instanceof ServerSentEvent || '[DONE]' === $chunk->getData()) {
                continue;
            }

            yield from $this->streamExtractor->extract(
                new JsonCrawler($chunk->getData())
            );
        }
    }
}
