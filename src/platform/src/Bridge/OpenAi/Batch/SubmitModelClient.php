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

use Symfony\AI\Platform\Batch\BatchSubmitClientInterface;
use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Uploads a JSONL file to the Files API, then creates a batch job referencing it.
 *
 * @see https://platform.openai.com/docs/api-reference/batch
 *
 * @author Camille Islasse <cams.development@gmail.com>
 */
final class SubmitModelClient extends AbstractModelClient implements BatchSubmitClientInterface
{
    private const RESPONSES_ENDPOINT = '/v1/responses';
    private const COMPLETION_WINDOW = '24h';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt && $model->supports(Capability::BATCH);
    }

    public function submitBatch(Model $model, iterable $requests, array $options = []): RawResultInterface
    {
        // OpenAI performs automatic prompt caching; cacheRetention is not an OpenAI
        // concept and must never be forwarded to the Responses API (mirrors Gpt\ModelClient::request).
        unset($options['cacheRetention']);

        $stream = fopen('php://temp', 'r+');

        try {
            foreach ($requests as $request) {
                fwrite($stream, json_encode([
                    'custom_id' => $request['id'],
                    'method' => 'POST',
                    'url' => self::RESPONSES_ENDPOINT,
                    'body' => array_merge(['model' => $model->getName()], $options, $request['payload']),
                ], \JSON_THROW_ON_ERROR)."\n");
            }

            if (0 === ftell($stream)) {
                throw new RuntimeException('Cannot submit an empty batch.');
            }

            rewind($stream);

            $inputFileId = $this->uploadFile($stream);
        } finally {
            fclose($stream);
        }

        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/batches', [
            'auth_bearer' => $this->apiKey,
            'json' => [
                'input_file_id' => $inputFileId,
                'endpoint' => self::RESPONSES_ENDPOINT,
                'completion_window' => self::COMPLETION_WINDOW,
            ],
        ]);

        return new RawHttpResult($response);
    }

    /**
     * @param resource $stream
     */
    private function uploadFile($stream): string
    {
        $response = $this->httpClient->request('POST', self::getBaseUrl($this->region).'/v1/files', [
            'auth_bearer' => $this->apiKey,
            'body' => [
                'purpose' => 'batch',
                'file' => $stream,
            ],
        ]);

        $data = $response->toArray();

        if (!isset($data['id'])) {
            throw new RuntimeException('Failed to upload batch input file: missing file ID in response.');
        }

        return $data['id'];
    }
}
