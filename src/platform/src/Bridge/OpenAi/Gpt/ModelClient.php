<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Gpt;

use Symfony\AI\Platform\Bridge\OpenAi\Batch\BatchClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Bridge\OpenAi\RegionAwareTrait;
use Symfony\AI\Platform\Bridge\OpenResponses\ModelClient as OpenResponsesModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\Stream\HttpStreamInterface;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient extends OpenResponsesModelClient
{
    use RegionAwareTrait;

    public const BATCH = 'batch';

    /**
     * The endpoint this model family talks to, and therefore the one its batches are submitted to.
     */
    public const PATH = '/v1/responses';

    private readonly BatchClient $batchClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $apiKey,
        ?string $region = null,
    ) {
        self::validateApiKey($apiKey);

        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        parent::__construct($httpClient, self::getBaseUrl($region), $apiKey, self::PATH);

        $this->batchClient = new BatchClient($httpClient, $apiKey, self::getBaseUrl($region), self::PATH);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        // OpenAI performs automatic prompt caching; no explicit cache_control
        // annotation is needed and cacheRetention is not an OpenAI concept.
        // Strip it so it is never forwarded to the Responses API.
        unset($options['cacheRetention']);

        if ($options[self::BATCH] ?? false) {
            unset($options[self::BATCH]);

            return $this->submitBatch($model, $payload, $options);
        }

        return parent::request($model, $payload, $options);
    }

    protected function createStreamParser(): HttpStreamInterface
    {
        // OpenAI always streams with a proper "text/event-stream" content type.
        return new SseStream();
    }

    /**
     * Turns the normalized inputs into one request body each, keyed by the identifier to report it back under.
     *
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     */
    private function submitBatch(Model $model, array|string $payload, array $options): RawHttpResult
    {
        if (!\is_array($payload) || [] === $payload) {
            throw new InvalidArgumentException(\sprintf('A batch invocation expects a non-empty array of inputs, "%s" given.', get_debug_type($payload)));
        }

        if ($options['stream'] ?? false) {
            throw new InvalidArgumentException('A batch is answered as a file hours later, so it cannot be streamed.');
        }

        $requests = [];

        foreach ($payload as $customId => $input) {
            // A single input normalizes into the keys of one Responses request, not into a map of them.
            if (\in_array($customId, ['input', 'instructions'], true)) {
                throw new InvalidArgumentException('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');
            }

            if (!\is_array($input) || !\is_array($input['input'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The input "%s" of the batch did not normalize into a request, a batch takes the same inputs as any other invocation - a "%s", for instance - one per identifier.', $customId, MessageBag::class));
            }

            $requests[$customId] = $this->createBody($model, $input, $options);
        }

        return $this->batchClient->submit($requests);
    }
}
