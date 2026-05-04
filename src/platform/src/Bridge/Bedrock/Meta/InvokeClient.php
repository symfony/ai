<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Meta;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\EndpointClientInterface;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * AWS Bedrock invoke client for Meta Llama models.
 *
 * Llama on Bedrock takes the upstream contract's payload as-is and returns
 * `{generation: "..."}`. Owns the Llama-specific Bedrock model-id
 * construction: `<region-prefix>.meta.<munged-name>-v1:0`, where munged-name
 * swaps `llama-3` → `llama3` and `.` → `-` (Bedrock's naming quirk for older
 * Llama generations).
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InvokeClient implements EndpointClientInterface
{
    public const ENDPOINT = 'bedrock.meta_llama_invoke';

    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
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

    public function getTokenUsageExtractor(): ?TokenUsageExtractorInterface
    {
        return null;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        if (\is_string($payload)) {
            throw new InvalidArgumentException(\sprintf('Payload must be an array, but a string was given to "%s".', self::class));
        }

        $invokeRequest = new InvokeModelRequest([
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ]);

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel($invokeRequest));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['generation'])) {
            throw new RuntimeException('Response does not contain any content.');
        }

        return new TextResult($data['generation']);
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);
        $modifiedModelName = str_replace('llama-3', 'llama3', $model->getName());

        return $regionPrefix.'.meta.'.str_replace('.', '-', $modifiedModelName).'-v1:0';
    }
}
