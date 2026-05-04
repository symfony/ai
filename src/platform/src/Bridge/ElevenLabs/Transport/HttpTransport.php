<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ElevenLabs\Transport;

use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\Exception\JsonException;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * ElevenLabs HTTP transport.
 *
 * Auth (xi-api-key) and the base URL are scoped on the underlying
 * HttpClient by the {@see Factory}; this transport just forwards. Routes
 * both JSON and multipart bodies based on the envelope's Content-Type.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $headers = $request->getHeaders();
        $contentType = $headers['Content-Type'] ?? $headers['content-type'] ?? 'application/json';

        $httpOptions = ['headers' => $headers];
        if ('multipart/form-data' === $contentType) {
            unset($httpOptions['headers']['Content-Type'], $httpOptions['headers']['content-type']);
            $httpOptions['body'] = $request->getPayload();
        } else {
            $httpOptions['json'] = $request->getPayload();
        }

        $response = $this->httpClient->request($request->getMethod(), $request->getPath(), $httpOptions);

        if (200 !== $response->getStatusCode()) {
            $message = self::extractErrorMessage($response)
                ?? \sprintf('The ElevenLabs API returned a non-successful status code "%d".', $response->getStatusCode());

            throw new RuntimeException($message);
        }

        return new RawHttpResult($response);
    }

    private static function extractErrorMessage(ResponseInterface $response): ?string
    {
        try {
            $data = $response->toArray(false);

            return $data['detail']['message'] ?? null;
        } catch (JsonException) {
            return null;
        }
    }
}
