<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\DockerModelRunner\Completions;

use Symfony\AI\Platform\Bridge\DockerModelRunner\Completions;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\PlatformOptions;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly EventSourceHttpClient $httpClient;
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of the Docker Model Runner instance, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        string $baseUrl,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Completions;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        /** @var array<string, mixed> $httpOptions */
        $httpOptions = $options[PlatformOptions::HTTP_OPTIONS] ?? [];
        unset($options[PlatformOptions::HTTP_OPTIONS]);

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/engines/v1/chat/completions', $this->baseUrl), [
            'headers' => ['Content-Type' => 'application/json'],
            'body' => $this->encodeJsonBody(array_merge($options, $payload)),
        ] + $httpOptions));
    }
}
