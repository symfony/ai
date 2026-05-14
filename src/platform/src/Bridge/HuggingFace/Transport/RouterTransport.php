<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Transport;

use Symfony\AI\Platform\Bridge\HuggingFace\Provider as HfProvider;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * HuggingFace router transport.
 *
 * Speaks router.huggingface.co with a configurable default inference
 * provider variant (HF inference vs cerebras / cohere / together / …).
 * Handlers know which provider they're talking to via `$options['provider']`
 * (defaulted by the {@see Factory}); the transport just prepends host +
 * provider segment to the envelope path. HTTP-level errors (404 / 503 /
 * generic 4xx with JSON or text body) become typed exceptions.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class RouterTransport implements TransportInterface
{
    private const HOST = 'https://router.huggingface.co';

    private readonly EventSourceHttpClient $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $defaultProvider = HfProvider::HF_INFERENCE,
    ) {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $provider = $options['provider'] ?? $this->defaultProvider;

        $headers = $request->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $url = self::HOST.'/'.ltrim(strtr($request->getPath(), ['{provider}' => $provider, '{name}' => $model->getName()]), '/');

        $httpOptions = [
            'auth_bearer' => $this->apiKey,
            'headers' => $headers,
        ];

        if ('multipart/form-data' === $contentType) {
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $request->getPayload();
        } else {
            $httpOptions['json'] = $request->getPayload();
        }

        $response = $this->httpClient->request($request->getMethod(), $url, $httpOptions);

        $status = $response->getStatusCode();

        if (503 === $status) {
            throw new RuntimeException('Service unavailable.');
        }

        if (404 === $status) {
            throw new InvalidArgumentException('Model, provider or task not found (404).');
        }

        if ($status >= 400 && $status < 500) {
            throw new InvalidArgumentException(\sprintf('API Client Error (%d): "%s"', $status, self::extractErrorMessage($response)));
        }

        if (200 !== $status) {
            throw new RuntimeException(\sprintf('Unhandled response code: %d', $status));
        }

        return new RawHttpResult($response);
    }

    private static function extractErrorMessage(\Symfony\Contracts\HttpClient\ResponseInterface $response): string
    {
        $contentType = $response->getHeaders(false)['content-type'][0] ?? '';

        if (str_contains($contentType, 'application/json')) {
            $data = json_decode($response->getContent(false), true);
            if (\is_array($data) && isset($data['error'])) {
                return \is_array($data['error']) ? (string) ($data['error'][0] ?? '') : (string) $data['error'];
            }
        }

        return $response->getContent(false);
    }
}
