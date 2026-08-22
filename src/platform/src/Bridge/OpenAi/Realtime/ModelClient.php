<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Realtime;

use Symfony\AI\Platform\Bridge\OpenAi\AbstractModelClient;
use Symfony\AI\Platform\Bridge\OpenAi\Realtime;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Saiful Islam Feroz <saiful.feroz@gmail.com>
 */
final class ModelClient extends AbstractModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        private readonly ?string $region = null,
    ) {
        self::validateApiKey($apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Realtime;
    }

    public function request(Model $model, array|string|object $payload, array $options = []): RawHttpResult
    {
        $body = array_merge($model->getOptions(), $options);
        $body['model'] = $model->getName();

        if (\is_array($payload)) {
            $body = array_merge($body, $payload);
        } elseif (\is_string($payload) && '' !== trim($payload)) {
            $body['instructions'] = $payload;
        }

        if (!isset($body['modalities'])) {
            $body['modalities'] = ['text', 'audio'];
        }

        if (!isset($body['voice'])) {
            $body['voice'] = 'alloy';
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1/realtime/sessions', self::getBaseUrl($this->region)), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => $body,
        ]));
    }
}
