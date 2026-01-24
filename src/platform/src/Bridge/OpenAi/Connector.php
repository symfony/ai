<?php

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\Contract\OpenAiContract;
use Symfony\AI\Platform\Connector\HttpResult;
use Symfony\AI\Platform\Connector\ResultInterface;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Connector\HttpConnector;
use Symfony\AI\Platform\Result\ResultInterface as ConverterResult;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class Connector extends HttpConnector
{
    const BASE_URL = 'https://api.openai.com/v1';

    public function __construct(
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
        private readonly ?HttpClientInterface $httpClient = null,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Gpt
            || $model instanceof DallE
            || $model instanceof Embeddings
            || $model instanceof Whisper;
    }

    public function getContract(): Contract
    {
        return OpenAiContract::create();
    }

    protected function initHttpClient(): EventSourceHttpClient
    {
        $httpClient = $this->httpClient instanceof EventSourceHttpClient
            ? $this->httpClient : new EventSourceHttpClient($this->httpClient);

        return $httpClient->withOptions(['auth_bearer' => $this->apiKey]);
    }

    protected function getEndpoint(Model $model): string
    {
        $baseUrl = match ($this->region) {
            null => 'https://api.openai.com',
            PlatformFactory::REGION_EU => 'https://eu.api.openai.com',
            PlatformFactory::REGION_US => 'https://us.api.openai.com',
            default => throw new InvalidArgumentException(\sprintf('Invalid region "%s". Valid options are: "%s", "%s", or null.', $this->region, PlatformFactory::REGION_EU, PlatformFactory::REGION_US)),
        };

        return match (get_class($model)) {
            Gpt::class => $baseUrl.'/chat/completions',
            DallE::class => $baseUrl.'/images/generations',
            Embeddings::class => $baseUrl.'/embeddings',
            Whisper::class => $baseUrl.'/audio/transcriptions',
            default => throw new InvalidArgumentException('Unsupported model type.'),
        };
    }

    public function isError(ResultInterface $result): bool
    {
        return false;
    }

    public function handleStream(Model $model, HttpResult|ResultInterface $result, array $options): StreamResult
    {
        return match (get_class($model)) {
            Gpt::class => (new Gpt\ResultConverter())->convert($result->getRawObject(), $options),
            default => throw new InvalidArgumentException('Unsupported model type for streaming.'),
        };
    }

    public function handleError(Model $model, ResultInterface $result): never
    {
        // TODO: Implement handleError() method.
    }

    public function handleResult(Model $model, HttpResult|ResultInterface $result, array $options): ConverterResult
    {
        return match (get_class($model)) {
            Gpt::class => (new Gpt\ResultConverter())->convert($result->getRawObject(), $options),
            DallE::class => (new DallEModelClient())->convert($result->getRawObject(), $options),
            Embeddings::class => (new EmbeddingsResponseConverter())->convert($result->getRawObject(), $options),
            Whisper::class => (new WhisperResponseConverter())->convert($result->getRawObject(), $options),
            default => throw new InvalidArgumentException('Unsupported model type for streaming.'),
        };
    }
}
