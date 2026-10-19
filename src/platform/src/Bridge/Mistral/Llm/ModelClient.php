<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Llm;

use Symfony\AI\Platform\Bridge\Mistral\Batch\BatchClient;
use Symfony\AI\Platform\Bridge\Mistral\Mistral;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public const BATCH = 'batch';

    /**
     * How long, in hours, Mistral may take for a batch. Stated on the job rather than on its
     * requests, and taken as an option rather than read back off the job: the created job does not
     * report the window it was given, so this is the only source the handle's maximum duration has.
     */
    public const TIMEOUT_HOURS = 'timeout_hours';

    /**
     * The endpoint this model family talks to, and therefore the one its batches are submitted to.
     */
    public const PATH = '/v1/chat/completions';

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;
    private readonly BatchClient $batchClient;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
        $this->batchClient = new BatchClient($this->httpClient, $apiKey, $this->baseUrl, self::PATH);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Mistral;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        if ($options[self::BATCH] ?? false) {
            unset($options[self::BATCH]);

            return $this->submitBatch($model, $payload, $options);
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.self::PATH, [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
            ],
            'body' => $this->encodeJsonBody(array_merge($options, $payload)),
        ]));
    }

    /**
     * Turns the normalized inputs into one request body each, keyed by the identifier to report it back under.
     *
     * @param array<string|int, mixed> $payload
     * @param array<string, mixed>     $options
     */
    private function submitBatch(Model $model, array $payload, array $options): RawHttpResult
    {
        if ([] === $payload) {
            throw new InvalidArgumentException(\sprintf('A batch invocation expects a non-empty array of inputs, "%s" given.', get_debug_type($payload)));
        }

        if ($options['stream'] ?? false) {
            throw new InvalidArgumentException('A batch is answered as a file hours later, so it cannot be streamed.');
        }

        $timeoutHours = $options[self::TIMEOUT_HOURS] ?? null;

        // The window belongs to the job Mistral creates, not to the requests it carries.
        unset($options[self::TIMEOUT_HOURS]);

        if (null !== $timeoutHours && (!\is_int($timeoutHours) || $timeoutHours < 1)) {
            throw new InvalidArgumentException(\sprintf('The timeout of a batch is stated in whole hours, "%s" given.', get_debug_type($timeoutHours)));
        }

        $requests = [];

        foreach ($payload as $customId => $input) {
            // A single input normalizes into the keys of one chat request, not into a map of them.
            if (\in_array($customId, ['messages', 'model'], true)) {
                throw new InvalidArgumentException('A batch invocation expects an array of inputs, keyed by the identifier to report each result under, and not a single input.');
            }

            if (!\is_array($input) || !\is_array($input['messages'] ?? null)) {
                throw new InvalidArgumentException(\sprintf('The input "%s" of the batch did not normalize into a request, a batch takes the same inputs as any other invocation - a "%s", for instance - one per identifier.', $customId, MessageBag::class));
            }

            // Mistral states the model on the job, and documents the lines without one.
            unset($input['model']);

            $requests[$customId] = array_merge($options, $input);
        }

        return $this->batchClient->submit($model->getName(), $requests, $timeoutHours);
    }
}
