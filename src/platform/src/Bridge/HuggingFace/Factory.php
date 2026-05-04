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

use Symfony\AI\Platform\Bridge\HuggingFace\Contract\HuggingFaceContract;
use Symfony\AI\Platform\Bridge\HuggingFace\Transport\RouterTransport;
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
     * @param non-empty-string $name
     */
    public static function createProvider(
        #[\SensitiveParameter] string $apiKey,
        string $provider = Provider::HF_INFERENCE,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
    ): ProviderInterface {
        $httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);

        $transport = new RouterTransport($httpClient, $apiKey, $provider);
        $clients = [
            new ChatCompletionClient($transport, $provider),
            new TextGenerationClient($transport),
            new ImageToTextClient($transport),
            new SummarizationClient($transport),
            new TranslationClient($transport),
            new AutomaticSpeechRecognitionClient($transport),
            new FeatureExtractionClient($transport),
            new TextRankingClient($transport),
            new TextClassificationClient($transport),
            new AudioClassificationClient($transport),
            new ImageClassificationClient($transport),
            new FillMaskClient($transport),
            new ImageSegmentationClient($transport),
            new ObjectDetectionClient($transport),
            new QuestionAnsweringClient($transport),
            new SentenceSimilarityClient($transport),
            new TableQuestionAnsweringClient($transport),
            new TokenClassificationClient($transport),
            new ZeroShotClassificationClient($transport),
            new TextToImageClient($transport),
        ];

        return new PlatformProvider(
            $name,
            $clients,
            $clients,
            $modelCatalog,
            $contract ?? HuggingFaceContract::create(),
            $eventDispatcher,
        );
    }

    /**
     * @param non-empty-string $name
     */
    public static function createPlatform(
        #[\SensitiveParameter] string $apiKey,
        string $provider = Provider::HF_INFERENCE,
        ?HttpClientInterface $httpClient = null,
        ModelCatalogInterface $modelCatalog = new ModelCatalog(),
        ?Contract $contract = null,
        ?EventDispatcherInterface $eventDispatcher = null,
        string $name = 'huggingface',
        ?ModelRouterInterface $modelRouter = null,
    ): Platform {
        return new Platform(
            [self::createProvider($apiKey, $provider, $httpClient, $modelCatalog, $contract, $eventDispatcher, $name)],
            $modelRouter ?? new CatalogBasedModelRouter(),
            $eventDispatcher,
        );
    }
}
