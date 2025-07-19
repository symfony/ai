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
use Symfony\AI\Platform\Bridge\Meta\Llama;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;

/**
 * @author Björn Altmann
 */
class LlamaModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Llama;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawBedrockResult
    {
        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest([
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ])));
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);
        $modifiedModelName = str_replace('llama-3', 'llama3', $model->getName());

        return $regionPrefix.'.meta.'.str_replace('.', '-', $modifiedModelName).'-v1:0';
    }
}
