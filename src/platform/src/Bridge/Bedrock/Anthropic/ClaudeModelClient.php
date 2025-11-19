<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock\Anthropic;

use AsyncAws\BedrockRuntime\BedrockRuntimeClient;
use AsyncAws\BedrockRuntime\Input\InvokeModelRequest;
use AsyncAws\BedrockRuntime\Result\InvokeModelResponse;
use Symfony\AI\Platform\Bridge\Anthropic\Claude;
use Symfony\AI\Platform\Bridge\Bedrock\RawBedrockResult;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelClientInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Björn Altmann
 */
final class ClaudeModelClient implements ModelClientInterface
{
    public function __construct(
        private readonly BedrockRuntimeClient $bedrockRuntimeClient,
        private readonly string $version = '2023-05-31',
    ) {
    }

    public function supports(Model $model): bool
    {
        return $model instanceof Claude;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawBedrockResult
    {
        unset($payload['model']);

        if (isset($options['tools'])) {
            $options['tool_choice'] = ['type' => 'auto'];
        }

        if (!isset($options['anthropic_version'])) {
            $options['anthropic_version'] = 'bedrock-'.$this->version;
        }

        $request = [
            'modelId' => $this->getModelId($model),
            'contentType' => 'application/json',
            'body' => json_encode(array_merge($options, $payload), \JSON_THROW_ON_ERROR),
        ];

        return new RawBedrockResult($this->bedrockRuntimeClient->invokeModel(new InvokeModelRequest($request)));
    }

    public function convert(InvokeModelResponse $bedrockResponse): ToolCallResult|TextResult
    {
        $data = json_decode($bedrockResponse->getBody(), true, 512, \JSON_THROW_ON_ERROR);

        if (!isset($data['content']) || [] === $data['content']) {
            throw new RuntimeException('Response does not contain any content.');
        }

        if (!isset($data['content'][0]['text']) && !isset($data['content'][0]['type'])) {
            throw new RuntimeException('Response content does not contain any text or type.');
        }

        $toolCalls = [];
        foreach ($data['content'] as $content) {
            if ('tool_use' === $content['type']) {
                $toolCalls[] = new ToolCall($content['id'], $content['name'], $content['input']);
            }
        }
        if ([] !== $toolCalls) {
            return new ToolCallResult(...$toolCalls);
        }

        return new TextResult($data['content'][0]['text']);
    }

    private function getModelId(Model $model): string
    {
        $configuredRegion = $this->bedrockRuntimeClient->getConfiguration()->get('region');
        $regionPrefix = substr((string) $configuredRegion, 0, 2);

        return $regionPrefix.'.anthropic.'.$model->getName().'-v1:0';
    }
}
