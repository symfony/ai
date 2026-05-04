<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decart;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;
use Symfony\AI\Platform\TransportInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

/**
 * Decart text-to-image / text-to-video contract handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class GenerateClient implements EndpointClientInterface
{
    public const ENDPOINT = 'decart.generate';

    public function __construct(
        private readonly TransportInterface $transport,
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
        $envelope = new RequestEnvelope(
            payload: [
                'prompt' => \is_string($payload) ? $payload : $payload['text'],
                ...$options,
            ],
            headers: ['Content-Type' => 'multipart/form-data'],
            path: \sprintf('/generate/%s', $model->getName()),
        );

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): BinaryResult
    {
        /** @var ResponseInterface $response */
        $response = $raw instanceof RawHttpResult ? $raw->getObject() : $raw->getObject();
        $headers = $response->getHeaders();

        return new BinaryResult($response->getContent(), $headers['content-type'][0]);
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
