<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * Shared default for HuggingFace per-task clients.
 *
 * The vast majority of HF tasks share one request shape: POST to
 * `/{provider}/models/{name}` with `{inputs, parameters}` JSON body. The
 * `{provider}` and `{name}` placeholders are resolved by
 * {@see Transport\RouterTransport} at send time. Concrete clients usually
 * only need to override {@see endpoint()} and {@see convert()}.
 *
 * Tasks that don't fit (chat-completion's URL split, text-ranking's pair
 * payload) override {@see request()} too.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
abstract class AbstractTaskClient implements EndpointClientInterface
{
    public function __construct(
        protected readonly TransportInterface $transport,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model->supportsEndpoint($this->endpoint());
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        // The "provider" option is consumed by the transport; strip it from
        // the request body so it doesn't leak through to the upstream API.
        unset($options['provider']);

        $body = ['inputs' => $payload];
        if ([] !== $options) {
            $body['parameters'] = $options;
        }

        $envelope = new RequestEnvelope(
            payload: $body,
            path: '/{provider}/models/{name}',
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
