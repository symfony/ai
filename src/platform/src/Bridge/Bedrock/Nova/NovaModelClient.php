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
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;

/**
 * @author Björn Altmann
 */
class NovaModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Nova;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawBedrockResult
    {
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

        $request = [
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode(array_merge($payload, $modelOptions), \JSON_THROW_ON_ERROR),
        ];

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest($request)));
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);

        return $regionPrefix.'.amazon.'.$model->getName().'-v1:0';
    }
}
