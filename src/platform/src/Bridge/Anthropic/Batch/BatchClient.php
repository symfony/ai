<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Anthropic\Batch;

use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Hands a set of requests to the Anthropic batch endpoint, which takes them inline.
 *
 * Where OpenAI wants the requests uploaded as a file first, Anthropic accepts them as the body of
 * the call creating the batch - so submitting one is a single request.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @internal
 */
final class BatchClient
{
    use JsonBodyEncodingTrait;

    /**
     * Anthropic rejects any other identifier, with an error naming neither the request nor the
     * identifier - so the batch is refused here instead, while it is still clear which one is meant.
     */
    private const CUSTOM_ID_PATTERN = '/^[a-zA-Z0-9_-]{1,64}$/';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly string $baseUrl,
    ) {
    }

    /**
     * @param array<string|int, array<string, mixed>> $requests     the request bodies, keyed by the
     *                                                              identifier to report them back under
     * @param list<string>                            $betaFeatures the beta features the requests need,
     *                                                              headed on the batch as a whole
     */
    public function submit(array $requests, array $betaFeatures = []): RawHttpResult
    {
        if ([] === $requests) {
            throw new InvalidArgumentException('A batch needs at least one request.');
        }

        $payload = [];

        foreach ($requests as $customId => $params) {
            $customId = (string) $customId;

            if (1 !== preg_match(self::CUSTOM_ID_PATTERN, $customId)) {
                throw new InvalidArgumentException(\sprintf('The identifier of a batch request must be 1 to 64 characters long and contain only letters, digits, hyphens and underscores, "%s" given.', $customId));
            }

            $payload[] = ['custom_id' => $customId, 'params' => $params];
        }

        $headers = [
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        if ([] !== $betaFeatures) {
            $headers['anthropic-beta'] = implode(',', $betaFeatures);
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1/messages/batches', [
            'headers' => $headers,
            'body' => $this->encodeJsonBody(['requests' => $payload]),
        ]));
    }
}
