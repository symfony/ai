<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAI\DallE;

use Symfony\AI\Platform\Bridge\OpenAI\DallE;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface as PlatformResponseFactory;
use Symfony\AI\Platform\Response\ResponseInterface as LlmResponse;
use Symfony\AI\Platform\ResponseConverterInterface as PlatformResponseConverter;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface as HttpResponse;
use Webmozart\Assert\Assert;

/**
 * @see https://platform.openai.com/docs/api-reference/images/create
 *
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final readonly class ModelClient implements PlatformResponseFactory, PlatformResponseConverter
{
    public function __construct(
        private HttpClientInterface $httpClient,
        #[\SensitiveParameter]
        private string $apiKey,
    ) {
        Assert::stringNotEmpty($apiKey, 'The API key must not be empty.');
        Assert::startsWith($apiKey, 'sk-', 'The API key must start with "sk-".');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof DallE;
    }

    public function request(Model $model, array|string $payload, array $options = []): HttpResponse
    {
        return $this->httpClient->request('POST', 'https://api.openai.com/v1/images/generations', [
            'auth_bearer' => $this->apiKey,
            'json' => array_merge($options, [
                'model' => $model->getName(),
                'prompt' => $payload,
            ]),
        ]);
    }

    public function convert(HttpResponse $response, array $options = []): LlmResponse
    {
        $response = $response->toArray();
        if (!isset($response['data'][0])) {
            throw new RuntimeException('No image generated.');
        }

        $images = [];
        foreach ($response['data'] as $image) {
            if ('url' === $options['response_format']) {
                $images[] = new UrlImage($image['url']);

                continue;
            }

            $images[] = new Base64Image($image['b64_json']);
        }

        return new ImageResponse($image['revised_prompt'] ?? null, ...$images);
    }
}
