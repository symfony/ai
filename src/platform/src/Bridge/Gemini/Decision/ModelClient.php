<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends decisions to the "POST /v1beta/decisions" endpoint of the Gemini API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Gemini-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://generativelanguage.googleapis.com',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Decision;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (!isset($options['questions'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires the "questions" option.', $model->getName()));
        }

        if (!\is_array($payload) || !isset($payload['input'])) {
            throw new InvalidArgumentException(\sprintf('The decision model "%s" requires a string, an array, a "Text", an "Image" or a "Collection" input.', $model->getName()));
        }

        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.'/v1beta/decisions', [
            'headers' => ['x-goog-api-key' => $this->apiKey],
            'json' => ['model' => $model->getName()] + $payload + ['questions' => (object) $options['questions']],
        ]));
    }
}
