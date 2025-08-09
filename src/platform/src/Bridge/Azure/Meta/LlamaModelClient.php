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

use Symfony\AI\Platform\Action;
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Exception\InvalidActionArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final readonly class LlamaModelClient implements ModelClientInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        #[\SensitiveParameter] private string $apiKey,
    ) {
    }

    public function supports(Model $model, Action $action): bool
    {
        if (Action::COMPLETE_CHAT !== $action && Action::CHAT !== $action) {
            return false;
        }

        return $model instanceof Llama;
    }

    public function request(Model $model, Action $action, array|string $payload, array $options = []): RawHttpResult
    {
        if (Action::COMPLETE_CHAT !== $action && Action::CHAT !== $action) {
            return throw new InvalidActionArgumentException($model, $action, [Action::CHAT, Action::COMPLETE_CHAT]);
        }

        $url = \sprintf('https://%s/chat/completions', $this->baseUrl);

        return new RawHttpResult($this->httpClient->request('POST', $url, [
            'headers' => [
                'Content-Type' => 'application/json',
                'Authorization' => $this->apiKey,
            ],
            'json' => array_merge($options, $payload),
        ]));
    }
}
