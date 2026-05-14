<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Ollama\Transport;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\NdjsonStream;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Component\HttpClient\EventSourceHttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Ollama HTTP transport.
 *
 * The base URL is set on the underlying HttpClient via ScopingHttpClient
 * upstream of construction (see {@see Factory}); this transport just
 * forwards. Streaming responses use NdjsonStream because Ollama emits
 * newline-delimited JSON, not SSE.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    private readonly EventSourceHttpClient $httpClient;

    public function __construct(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient instanceof EventSourceHttpClient ? $httpClient : new EventSourceHttpClient($httpClient);
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $response = $this->httpClient->request($request->getMethod(), $request->getPath(), [
            'headers' => array_merge(['Content-Type' => 'application/json'], $request->getHeaders()),
            'json' => $request->getPayload(),
        ]);

        return new RawHttpResult($response, new NdjsonStream());
    }
}
