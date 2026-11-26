<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Mistral\Moderation;

use Symfony\AI\Platform\Bridge\Mistral\Moderation;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Anthony Hacart <anthony.hacart@gmail.com>
 */
final class ModelClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    private readonly string $baseUrl;

    /**
     * @param string $baseUrl Base URL of a Mistral-compatible endpoint, with or without a trailing slash
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[\SensitiveParameter] private readonly string $apiKey,
        string $baseUrl = 'https://api.mistral.ai',
    ) {
        $this->baseUrl = rtrim($baseUrl, '/');
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Moderation;
    }

    /**
     * @param array<string, mixed> $options
     */
    public function request(Model $model, array|string $payload, array $options = []): RawHttpResult
    {
        if (\is_string($payload)) {
            return $this->moderate('/v1/moderations', $model, $payload, $options);
        }

        if (self::isConversation($payload)) {
            return $this->moderate('/v1/chat/moderations', $model, $payload['messages'], $options);
        }

        if (self::isText($payload)) {
            return $this->moderate('/v1/moderations', $model, $payload['text'], $options);
        }

        $texts = [];
        $conversations = [];
        foreach ($payload as $key => $item) {
            if (!\is_int($key)) {
                throw new InvalidArgumentException('The payload must be a text, a list of texts, a conversation or a list of conversations.');
            }

            if (self::isConversation($item)) {
                $conversations[] = $item['messages'];
            } elseif (self::isText($item)) {
                $texts[] = $item['text'];
            } elseif (\is_string($item)) {
                $texts[] = $item;
            } else {
                throw new InvalidArgumentException('The payload must be a text, a list of texts, a conversation or a list of conversations.');
            }
        }

        if ([] === $conversations) {
            return $this->moderate('/v1/moderations', $model, $texts, $options);
        }

        if ([] !== $texts) {
            throw new InvalidArgumentException('Texts and conversations cannot be moderated in the same request.');
        }

        return $this->moderate('/v1/chat/moderations', $model, $conversations, $options);
    }

    /**
     * @phpstan-assert-if-true array{messages: list<array<string, mixed>>} $payload
     */
    private static function isConversation(mixed $payload): bool
    {
        return \is_array($payload) && isset($payload['messages']) && \is_array($payload['messages']);
    }

    /**
     * @phpstan-assert-if-true array{text: string} $payload
     */
    private static function isText(mixed $payload): bool
    {
        return \is_array($payload) && isset($payload['text']) && \is_string($payload['text']);
    }

    /**
     * @param list<mixed>|string   $input
     * @param array<string, mixed> $options
     */
    private function moderate(string $path, Model $model, array|string $input, array $options): RawHttpResult
    {
        return new RawHttpResult($this->httpClient->request('POST', $this->baseUrl.$path, [
            'auth_bearer' => $this->apiKey,
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => $this->encodeJsonBody(array_merge($options, ['model' => $model->getName(), 'input' => $input])),
        ]));
    }
}
