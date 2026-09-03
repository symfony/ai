<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;

use Symfony\AI\Platform\Bridge\EdenAi\ErrorHandlingTrait;
use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\FileUploader;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\UniversalAiPayloadTrait;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\Clock\Clock;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Client for the Eden AI speech-to-text expert models, exposed through the asynchronous
 * /v3/universal-ai/async endpoint.
 *
 * Some providers answer synchronously, others return a job in "processing" state which
 * is polled until it reaches a terminal status. Pass a "webhook_receiver" option instead
 * to be notified out of band rather than blocking on the poll loop.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClient implements ModelClientInterface
{
    use ErrorHandlingTrait;
    use UniversalAiPayloadTrait;

    private const DEFAULT_POLL_INTERVAL_SECONDS = 1;
    private const DEFAULT_MAX_POLLS = 120;

    private readonly ClockInterface $clock;
    private readonly FileUploader $fileUploader;

    /**
     * @param int $maxPolls            how many times a pending job is polled before giving up
     * @param int $pollIntervalSeconds how long to wait between two polls
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?ClockInterface $clock = null,
        ?FileUploader $fileUploader = null,
        private readonly int $maxPolls = self::DEFAULT_MAX_POLLS,
        private readonly int $pollIntervalSeconds = self::DEFAULT_POLL_INTERVAL_SECONDS,
    ) {
        if ($maxPolls < 1) {
            throw new InvalidArgumentException(\sprintf('The maximum number of polls must be at least 1, "%d" given.', $maxPolls));
        }

        if ($pollIntervalSeconds < 1) {
            throw new InvalidArgumentException(\sprintf('The poll interval must be at least 1 second, "%d" given.', $pollIntervalSeconds));
        }

        $this->clock = $clock ?? new Clock();
        $this->fileUploader = $fileUploader ?? new FileUploader($httpClient, $baseUrl, $apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $response = $this->httpClient->request('POST', $this->baseUrl.'/v3/universal-ai/async', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $this->createRequestBody($model, $payload, $options),
        ]);

        $data = $this->decode($response);

        for ($poll = 0; $this->isPending($data); ++$poll) {
            if ($poll >= $this->maxPolls) {
                throw new RuntimeException(\sprintf('Eden AI speech-to-text job "%s" did not complete in time.', $data['public_id'] ?? 'unknown'));
            }

            if (!isset($data['public_id']) || !\is_string($data['public_id'])) {
                throw new RuntimeException('Eden AI async job response does not contain a public_id.');
            }

            $this->clock->sleep($this->pollIntervalSeconds);

            $response = $this->httpClient->request('GET', $this->baseUrl.'/v3/universal-ai/async/'.$data['public_id'], [
                'auth_bearer' => $this->apiKey,
            ]);

            $data = $this->decode($response);
        }

        return new RawHttpResult($response);
    }

    /**
     * Validates the status before decoding: an error response can carry an empty or
     * non-JSON body, which would otherwise surface as an HttpClient decoding exception
     * instead of a platform one.
     *
     * @return array<string, mixed>
     */
    private function decode(ResponseInterface $response): array
    {
        $this->assertSuccessfulResponse($response, [200, 202]);

        try {
            return $response->toArray(false);
        } catch (DecodingExceptionInterface|TransportExceptionInterface $e) {
            throw new RuntimeException('Could not decode the Eden AI asynchronous job response.', 0, $e);
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function isPending(array $data): bool
    {
        return \in_array($data['status'] ?? null, ['pending', 'processing'], true);
    }

    private function getFileUploader(): FileUploader
    {
        return $this->fileUploader;
    }
}
