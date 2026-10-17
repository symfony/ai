<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Perplexity;

use Symfony\AI\Platform\Bridge\OpenResponses\ModelClient as OpenResponsesModelClient;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\Stream\HttpStreamInterface;
use Symfony\AI\Platform\Result\Stream\SseStream;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Talks to the Perplexity Agent API, which follows the Responses API schema.
 *
 * @author Mathieu Santostefano <msantostefano@proton.me>
 */
final class ModelClient extends OpenResponsesModelClient
{
    /**
     * Sonar Chat Completions models and the preset Perplexity recommends in their place.
     */
    private const SONAR_MODEL_PRESETS = [
        'sonar' => 'fast',
        'sonar-pro' => 'fast',
        'sonar-reasoning' => 'low',
        'sonar-reasoning-pro' => 'low',
        'sonar-deep-research' => 'high',
    ];

    /**
     * @param string $baseUrl Base URL of a Perplexity-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        HttpClientInterface $httpClient,
        #[\SensitiveParameter] string $apiKey,
        string $baseUrl = 'https://api.perplexity.ai',
    ) {
        if ('' === $apiKey) {
            throw new InvalidArgumentException('The API key must not be empty.');
        }

        if (!str_starts_with($apiKey, 'pplx-')) {
            throw new InvalidArgumentException('The API key must start with "pplx-".');
        }

        parent::__construct($httpClient, $baseUrl, $apiKey, '/v1/agent');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Perplexity;
    }

    /**
     * Unlike the Responses API, the Agent API takes structured output as a top-level "response_format".
     */
    protected function createBody(Model $model, array $payload, array $options): array
    {
        if (isset($options['max_tokens'])) {
            $options['max_output_tokens'] ??= $options['max_tokens'];
            unset($options['max_tokens']);
        }

        $name = $model->getName();

        if (isset(self::SONAR_MODEL_PRESETS[$name])) {
            @trigger_error(\sprintf('Since symfony/ai-perplexity-platform 0.15: The "%s" model is deprecated, use the "%s" preset instead.', $name, self::SONAR_MODEL_PRESETS[$name]), \E_USER_DEPRECATED);

            $name = self::SONAR_MODEL_PRESETS[$name];
        }

        // Agent API model identifiers are provider-prefixed, e.g. "perplexity/sonar"; anything else names a preset
        $target = str_contains($name, '/') ? ['model' => $name] : ['preset' => $name];

        return array_merge($options, $target, $payload);
    }

    protected function createStreamParser(): HttpStreamInterface
    {
        return new SseStream();
    }
}
