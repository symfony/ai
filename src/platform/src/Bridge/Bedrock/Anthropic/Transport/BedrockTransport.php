<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Anthropic\Transport;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\TransportInterface;

/**
 * AWS Bedrock transport for Anthropic Claude models.
 *
 * Owns model-id rewriting (region-prefix + version-suffix), the
 * `anthropic_version` body injection Bedrock requires, and the AsyncAws
 * SDK invocation. Path/method on the envelope are ignored — Bedrock
 * routes by model id, not URL.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class BedrockTransport implements TransportInterface
{
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
        private readonly string $version = '2023-05-31',
    ) {
    }

    public function send(Model $model, RequestEnvelope $request, array $options = []): RawResultInterface
    {
        $payload = $request->getPayload();

        // Bedrock rejects requests carrying `model` in the body — model selection
        // happens via the `modelId` request property below.
        unset($payload['model']);

        if (!isset($payload['anthropic_version'])) {
            $payload['anthropic_version'] = 'bedrock-'.$this->version;
        }

        $invokeRequest = new InvokeModelRequest([
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode($payload, \JSON_THROW_ON_ERROR),
        ]);

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel($invokeRequest));
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);

        return $regionPrefix.'.anthropic.'.$model->getName().'-v1:0';
    }
}
