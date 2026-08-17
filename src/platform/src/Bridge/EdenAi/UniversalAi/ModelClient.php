<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\EdenAi\UniversalAi;

use Symfony\AI\Platform\Bridge\EdenAi\DocumentParser;
use Symfony\AI\Platform\Bridge\EdenAi\ImageAnalysis;
use Symfony\AI\Platform\Bridge\EdenAi\ImageGeneration;
use Symfony\AI\Platform\Bridge\EdenAi\Ocr;
use Symfony\AI\Platform\Bridge\EdenAi\Tts;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the synchronous Eden AI expert models, exposed through the single
 * /v3/universal-ai endpoint.
 *
 * The payload is either the normalized array coming from the bridge normalizers or a
 * plain string: a direct file URL or a file ID from POST /v3/upload for file-based
 * models, the text to process for text-based models (TTS, image generation). Binary
 * content (a "file_data" payload entry) is transparently uploaded through /v3/upload
 * first. Any option that is not a root-level request field is forwarded as part of the
 * "input" object (e.g. "language", "document_type" or "voice").
 *
 * @see https://www.edenai.co/docs
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
        return $model instanceof Ocr
            || $model instanceof DocumentParser
            || $model instanceof ImageAnalysis
            || $model instanceof ImageGeneration
            || $model instanceof Tts;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v3/universal-ai', [
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
