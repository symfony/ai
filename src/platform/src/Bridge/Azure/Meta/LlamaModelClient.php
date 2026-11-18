<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Azure\Meta;

use Symfony\AI\Platform\Bridge\Azure\BaseUrlNormalizerTrait;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class LlamaModelClient implements ModelClientInterface
{
    use BaseUrlNormalizerTrait;
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Azure resource; accepts a bare host (https assumed) or a
     *                        full URL with scheme, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        string $baseUrl,
        #[\SensitiveParameter] private readonly string $apiKey,
    ) {
        $this->baseUrl = $this->normalizeBaseUrl($baseUrl);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        $url = $this->baseUrl.'/chat/completions';

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey,
            ],
            'body' => $this->encodeJsonBody(array_merge($options, $payload)),
        ] + $httpOptions));
    }
}
