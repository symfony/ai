<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Generic;

use Symfony\AI\Platform\Bridge\Generic\Completions\CompletionsConversionTrait;
use Symfony\AI\Platform\Bridge\Generic\Completions\TokenUsageExtractor;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\ChoiceResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\TransportInterface;

/**
 * OpenAI-compatible `/v1/chat/completions` contract handler.
 *
 * The de facto standard wire shape: most non-OpenAI providers (Mistral,
 * Cerebras, Scaleway, Ovh, DeepSeek, LM Studio, Perplexity, Groq, …) speak
 * exactly this format — they only differ in base URL and auth, both of
 * which are transport concerns. One handler covers all of them.
 *
 * Path is configurable so providers like Perplexity that use a non-standard
 * suffix can override it.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionsClient implements EndpointClientInterface
{
    use CompletionsConversionTrait;
    public const ENDPOINT = 'openai_compatible.chat_completions';

    public function __construct(
        private readonly TransportInterface $transport,
        private readonly string $path = '/v1/chat/completions',
    ) {
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint(self::ENDPOINT);
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        // cacheRetention is an Anthropic-only Symfony AI option; OpenAI-compatible
        // endpoints reject unknown body fields with 400, so strip it here.
        unset($options['cacheRetention']);

        $envelope = new RequestEnvelope(
            payload: array_merge($options, $payload, ['model' => $model->getName()]),
            path: $this->path,
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): ResultInterface
    {
        if ($options['stream'] ?? false) {
            return new StreamResult($this->convertStream($raw));
        }

        $data = $raw->getData();

        if (isset($data['error']['code']) && 'content_filter' === $data['error']['code']) {
            throw new ContentFilterException($data['error']['message']);
        }

        if (isset($data['error'])) {
            throw new RuntimeException(\sprintf('Error "%s"-%s (%s): "%s".', $data['error']['code'] ?? '-', $data['error']['type'] ?? '-', $data['error']['param'] ?? '-', $data['error']['message'] ?? '-'));
        }

        if (!isset($data['choices'])) {
            throw new RuntimeException('Response does not contain choices.');
        }

        $choices = array_map($this->convertChoice(...), $data['choices']);

        return 1 === \count($choices) ? $choices[0] : new ChoiceResult($choices);
    }

    public function getTokenUsageExtractor(): TokenUsageExtractor
    {
        return new TokenUsageExtractor();
    }
}
