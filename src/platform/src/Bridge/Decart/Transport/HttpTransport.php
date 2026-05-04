<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart\Transport;

use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Decart HTTP transport (api.decart.ai, x-api-key auth).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class HttpTransport implements TransportInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $hostUrl = 'https://api.decart.ai/v1',
    ) {
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        return new RawHttpResult($this->httpClient->request($request->getMethod(), rtrim($this->hostUrl, '/').$request->getPath(), [
            'headers' => array_merge(['x-api-key' => $this->apiKey], $request->getHeaders()),
            'body' => $request->getPayload(),
        ]));
    }
}
