<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace\Completions;

use Symfony\AI\Platform\Bridge\Generic\Completions\ModelClient as GenericModelClient;
use Symfony\AI\Platform\Bridge\HuggingFace\Completions;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ModelClient implements ModelClientInterface
{
    private readonly GenericModelClient $modelClient;

    /**
     * @param ?string $provider Inference provider to route requests to, Hugging Face picks one when null
     * @param string  $baseUrl  Base URL of the Hugging Face inference router, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $apiKey,
        private readonly ?string $provider = null,
        string $baseUrl = 'https://router.huggingface.co',
    ) {
        $this->modelClient = new GenericModelClient($httpClient, $baseUrl, $apiKey);
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Completions;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        $provider = $options['provider'] ?? $this->provider;
        unset($options['provider']);

        // The router maps the Hugging Face model ID to the provider's own ID via the "<model>:<provider>" suffix
        if (\is_array($payload) && null !== $provider && !str_contains($model->getName(), ':')) {
            $payload['model'] = \sprintf('%s:%s', $model->getName(), $provider);
        }

        return $this->modelClient->request($model, $payload, $options);
    }
}
