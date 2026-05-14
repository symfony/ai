<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Replicate;

use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * Replicate predictions client for Meta Llama models.
 *
 * Owns the vendor-prefix construction (`meta/meta-{name}` for Llama) and
 * delegates the polling loop to {@see Client}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class MetaPredictionsClient implements EndpointClientInterface
{
    public const ENDPOINT = 'replicate.meta_predictions';

    private const VENDOR_PREFIX = 'meta/meta-';

    public function __construct(
        private readonly Client $client,
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

        $modelSlug = self::VENDOR_PREFIX.$model->getName();

        return new RawHttpResult($this->client->request($modelSlug, 'predictions', $payload));
    }

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        $data = $raw->getData();

        if (!isset($data['output'])) {
            throw new RuntimeException('Response does not contain output.');
        }

        return new TextResult(implode('', $data['output']));
    }

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }
}
