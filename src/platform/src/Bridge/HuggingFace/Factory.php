<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\Generic\Completions\ResultConverter as CompletionsResultConverter;
use Symfony\AI\Platform\Bridge\HuggingFace\Contract\HuggingFaceContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\ModelCatalog\ModelCatalogInterface;
use Symfony\AI\Platform\ModelRouter\CatalogBasedModelRouter;
use Symfony\AI\Platform\ModelRouterInterface;
use Symfony\AI\Platform\Platform;
use Symfony\AI\Platform\Provider as PlatformProvider;
use Symfony\AI\Platform\ProviderInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class Factory
{
    /**
     * @param ?string          $provider Inference provider for chat completion, Hugging Face picks one when null
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
        string $baseUrl = 'https://router.huggingface.co',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        return new PlatformProvider(
            $name,
            [
                new Completions\ModelClient($httpClient, $apiKey, $provider, $baseUrl),
                new Pipeline\ModelClient($httpClient, $apiKey, $baseUrl),
            ],
            [
                new CompletionsResultConverter(),
                new Classification\ResultConverter(),
                new Embeddings\ResultConverter(),
                new FillMask\ResultConverter(),
                new ImageSegmentation\ResultConverter(),
                new ObjectDetection\ResultConverter(),
                new QuestionAnswering\ResultConverter(),
                new Reranker\ResultConverter(),
                new SpeechRecognition\ResultConverter(),
                new Summarization\ResultConverter(),
                new TableQuestionAnswering\ResultConverter(),
                new TextToImage\ResultConverter(),
                new TokenClassification\ResultConverter(),
                new Translation\ResultConverter(),
                new ZeroShotClassification\ResultConverter(),
            ],
            $modelCatalog,
            $contract ?? HuggingFaceContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param ?string          $provider Inference provider for chat completion, Hugging Face picks one when null
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
        ?ModelRouterInterface $modelRouter = null,
        string $baseUrl = 'https://router.huggingface.co',
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $provider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name, $baseUrl)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }

    /**
     * @param ?string $provider Inference provider whose chat models the catalog returns
     */
    public static function createModelCatalog(
        #[\SensitiveParameter] string $apiKey,
        ?string $provider = null,
        ?HttpClientInterface $httpClient = null,
    ): ModelCatalogInterface {
        return new ModelApiCatalog(new ApiClient($httpClient, $apiKey), $provider);
    }
}
