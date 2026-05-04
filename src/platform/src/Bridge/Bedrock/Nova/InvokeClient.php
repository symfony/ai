<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Nova;

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
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

/**
 * AWS Bedrock invoke client for Amazon Nova models.
 *
 * Nova on Bedrock takes the upstream contract's payload and folds top-level
 * options (`tools`, `temperature`, `max_tokens`) into the Bedrock-Converse-style
 * `toolConfig`/`inferenceConfig` body sub-objects. The response shape is
 * `{output: {message: {content: [{text: ...} | {toolUse: ...}]}}}`. Owns the
 * Nova-specific Bedrock model-id construction:
 * `<region-prefix>.amazon.<name>-v1:0`.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class InvokeClient implements EndpointClientInterface
{
    public const ENDPOINT = 'bedrock.nova_invoke';

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

        unset($payload['model']);

        $modelOptions = [];
        if (isset($options['tools'])) {
            $modelOptions['toolConfig']['tools'] = $options['tools'];
        }

        if (isset($options['temperature'])) {
            $modelOptions['inferenceConfig']['temperature'] = $options['temperature'];
        }

        if (isset($options['max_tokens'])) {
            $modelOptions['inferenceConfig']['maxTokens'] = $options['max_tokens'];
        }

        $invokeRequest = new InvokeModelRequest([
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode(array_merge($payload, $modelOptions), \JSON_THROW_ON_ERROR),
        ]);

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel($invokeRequest));
    }

    public function convert(RawResultInterface $result, array $options = []): ResultInterface
    {
        $data = $result->getData();

        if (!isset($data['output']) || [] === $data['output']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        if (!isset($data['output']['message']['content'][0]['text'])) {
            throw new RuntimeException('Response content does not contain any text.');
        }

        $toolCalls = [];
        foreach ($data['output']['message']['content'] as $content) {
            if (isset($content['toolUse'])) {
                $toolCalls[] = new ToolCall(
                    $content['toolUse']['toolUseId'],
                    $content['toolUse']['name'],
                    $content['toolUse']['input'],
                );
            }
        }

        if ([] !== $toolCalls) {
            return new ToolCallResult($toolCalls);
        }

        return new TextResult($data['output']['message']['content'][0]['text']);
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);

        return $regionPrefix.'.amazon.'.$model->getName().'-v1:0';
    }
}
