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
        $session = array_merge($model->getOptions(), $options);
        $session['model'] = $model->getName();

        if (\is_array($payload)) {
            $session = array_merge($session, $payload);
        } elseif (\is_string($payload) && '' !== trim($payload)) {
            $session['instructions'] = $payload;
        }

        if (!isset($session['type'])) {
            $session['type'] = 'realtime';
        }

        if (!isset($session['modalities'])) {
            $session['modalities'] = ['text', 'audio'];
        }

        if (isset($session['voice'])) {
            $session['audio']['output']['voice'] = $session['voice'];
            unset($session['voice']);
        } elseif (!isset($session['audio']['output']['voice'])) {
            $session['audio']['output']['voice'] = 'alloy';
        }

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('%s/v1/realtime/client_secrets', self::getBaseUrl($this->region)), [
            'auth_bearer' => $this->apiKey,
            'headers' => ['Content-Type' => 'application/json'],
            'json' => ['session' => $session],
        ]));
    }
}
