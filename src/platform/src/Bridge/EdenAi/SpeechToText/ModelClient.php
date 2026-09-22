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

use Symfony\AI\Platform\Bridge\EdenAi\SpeechToText;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\FileUploader;
use Symfony\AI\Platform\Bridge\EdenAi\UniversalAi\UniversalAiPayloadTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Eden AI speech-to-text expert models, exposed through the asynchronous
 * /v3/universal-ai/async endpoint.
 *
 * The request is submitted and nothing else: some providers answer it with the transcription
 * already in the body, others with a job still running. Which of the two happened is read by
 * {@see ResultConverter}, and a job is resolved through
 * {@see \Symfony\AI\Platform\Bridge\EdenAi\EdenAiJobClient}.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClient implements ModelClientInterface
{
    use UniversalAiPayloadTrait;

    private readonly FileUploader $fileUploader;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
        ?FileUploader $fileUploader = null,
    ) {
        $this->fileUploader = $fileUploader ?? new FileUploader($httpClient, $baseUrl, $apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof SpeechToText;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v3/universal-ai/async', [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $this->createRequestBody($model, $payload, $options),
        ]));
    }

    private function getFileUploader(): FileUploader
    {
        return $this->fileUploader;
    }
}
