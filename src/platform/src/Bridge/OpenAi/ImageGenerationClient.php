<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi;

use Symfony\AI\Platform\Bridge\OpenAi\DallE\Base64Image;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\ImageResult;
use Symfony\AI\Platform\Bridge\OpenAi\DallE\UrlImage;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\StructuredOutput\PlatformSubscriber;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI /v1/images/generations contract handler (DALL-E).
 *
 * @see https://platform.openai.com/docs/api-reference/images/create
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ImageGenerationClient implements EndpointClientInterface
{
    public const ENDPOINT = 'openai.images_generations';

    public function __construct(
        private readonly TransportInterface $transport,
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $envelope = new RequestEnvelope(
            payload: array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $payload,
            ]),
            path: '/v1/images/generations',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ImageResult
    {
        $data = $raw->getData();

        if (!isset($data['data'][0])) {
            throw new RuntimeException('No image generated.');
        }

        $images = [];
        foreach ($data['data'] as $image) {
            if ('url' === ($options[PlatformSubscriber::RESPONSE_FORMAT] ?? null)) {
                $images[] = new UrlImage($image['url']);
                continue;
            }

            $images[] = new Base64Image($image['b64_json']);
        }

        return new ImageResult($image['revised_prompt'] ?? null, $images);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
