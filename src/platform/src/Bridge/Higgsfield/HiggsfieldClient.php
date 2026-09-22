<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Higgsfield;

use Symfony\AI\Platform\JsonBodyEncodingTrait;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Client for the Higgsfield API (https://higgsfield.ai).
 *
 * Submits a generation and answers with its request identifier; resolving it is the job of
 * {@see HiggsfieldJobClient}.
 *
 * @author Oskar Stark <oskarstark@googlemail.com>
 */
final class HiggsfieldClient implements ModelClientInterface
{
    use JsonBodyEncodingTrait;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Higgsfield;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $endpoint = ltrim($model->getName(), '/');

        return new RawHttpResult($this->httpClient->request('POST', \sprintf('/%s', $endpoint), [
            'body' => $this->encodeJsonBody($this->createInput($payload, $options)),
            'headers' => ['Content-Type' => 'application/json'],
        ]));
    }

    /**
     * @param array<string|int, mixed>|string $payload
     * @param array<string, mixed>            $options
     *
     * @return array<string, mixed>
     */
    private function createInput(array|string $payload, array $options): array
    {
        if (\is_string($payload)) {
            return ['prompt' => $payload, ...$options];
        }

        // Image content normalized by the ImageNormalizer, e.g. for image-to-video generation.
        if (isset($payload['type'], $payload['image_url']) && 'image_url' === $payload['type']) {
            return ['image_url' => $payload['image_url'], ...$options];
        }

        // Text content normalized to ['text' => '...'] by the default contract.
        if (isset($payload['text'])) {
            return ['prompt' => $payload['text'], ...$options];
        }

        return [...$payload, ...$options];
    }
}
