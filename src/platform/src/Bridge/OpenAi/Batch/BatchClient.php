<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Batch;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\HttpStatusErrorHandlingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Hands a set of requests to the OpenAI batch endpoint, which takes them as an uploaded JSONL file.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class BatchClient
{
    use HttpStatusErrorHandlingTrait;
    use JsonBodyEncodingTrait;

    /**
     * OpenAI rejects longer identifiers, with an error naming neither the line nor the identifier.
     */
    private const MAX_CUSTOM_ID_LENGTH = 64;

    /**
     * The only window OpenAI currently accepts.
     */
    private const COMPLETION_WINDOW = '24h';

    /**
     * @param string $path the endpoint the batched requests are for
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl,
        private readonly string $path,
    ) {
    }

    /**
     * @param array<string|int, array<string, mixed>> $requests the request bodies, keyed by the
     *                                                          identifier to report them back under
     */
    public function submit(array $requests): RawHttpResult
    {
        if ([] === $requests) {
            throw new InvalidArgumentException('A batch needs at least one request.');
        }

        $fileId = $this->upload($requests);

        $response = $this->httpClient->request('POST', $this->baseUrl.'/v1/batches', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody([
                'input_file_id' => $fileId,
                'endpoint' => $this->path,
                'completion_window' => self::COMPLETION_WINDOW,
            ]),
        ]);

        try {
            // A rejected batch will never read its input file, so it is deleted here rather than
            // left behind; reporting the rejection stays with the result converter.
            if (400 <= $response->getStatusCode()) {
                $this->delete($fileId);
            }
        } catch (TransportExceptionInterface) {
            // Whether the batch was created is unknown, so its input file has to stay.
        }

        return new RawHttpResult($response);
    }

    private function delete(string $fileId): void
    {
        try {
            $this->httpClient->request('DELETE', \sprintf('%s/v1/files/%s', $this->baseUrl, urlencode($fileId)), [
                'auth_bearer' => $this->apiKey,
            ])->getStatusCode();
        } catch (ExceptionInterface) {
            // Best effort: a leftover input file costs nothing but a slot in the account.
        }
    }

    /**
     * @param array<string|int, array<string, mixed>> $requests
     */
    private function upload(array $requests): string
    {
        $file = fopen('php://temp', 'r+');

        if (false === $file) {
            throw new RuntimeException('Unable to open a temporary stream for the batch file.');
        }

        // OpenAI rejects an upload whose file part it cannot recognize as JSONL.
        stream_context_set_option($file, 'http', 'filename', 'batch.jsonl');
        stream_context_set_option($file, 'http', 'content_type', 'application/jsonl');

        foreach ($requests as $customId => $body) {
            $customId = (string) $customId;

            if (self::MAX_CUSTOM_ID_LENGTH < \strlen($customId)) {
                throw new InvalidArgumentException(\sprintf('The identifier of a batch request must be at most %d characters long, "%s" given.', self::MAX_CUSTOM_ID_LENGTH, $customId));
            }

            fwrite($file, $this->encodeJsonBody([
                'custom_id' => $customId,
                'method' => 'POST',
                'url' => $this->path,
                'body' => $body,
            ])."\n");
        }

        rewind($file);

        $response = $this->httpClient->request('POST', $this->baseUrl.'/v1/files', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'multipart/form-data'],
            'body' => ['purpose' => 'batch', 'file' => $file],
        ]);

        $this->throwOnHttpError($response);

        $fileId = $response->toArray(false)['id'] ?? null;

        if (!\is_string($fileId) || '' === $fileId) {
            throw new RuntimeException('The upload of the batch file did not return a file identifier.');
        }

        return $fileId;
    }
}
