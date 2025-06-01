<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Bedrock;

use Symfony\AI\Platform\Bridge\Anthropic\Contract as AnthropicContract;
use Symfony\AI\Platform\Bridge\Bedrock\Nova\Contract as NovaContract;
use Symfony\AI\Platform\Bridge\Meta\Contract as LlamaContract;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Response\ResponseInterface;

/**
 * @author Björn Altmann
 */
class Platform implements PlatformInterface
{
    /**
     * @var BedrockModelClient[]
     */
    private readonly array $modelClients;

    /**
     * @param iterable<BedrockModelClient> $modelClients
     */
    public function __construct(
        iterable $modelClients,
        private ?Contract $contract = null,
    ) {
        $this->contract = $contract ?? Contract::create(
            new AnthropicContract\AssistantMessageNormalizer(),
            new AnthropicContract\DocumentNormalizer(),
            new AnthropicContract\DocumentUrlNormalizer(),
            new AnthropicContract\ImageNormalizer(),
            new AnthropicContract\ImageUrlNormalizer(),
            new AnthropicContract\MessageBagNormalizer(),
            new AnthropicContract\ToolCallMessageNormalizer(),
            new AnthropicContract\ToolNormalizer(),
            new LlamaContract\MessageBagNormalizer(),
            new NovaContract\AssistantMessageNormalizer(),
            new NovaContract\MessageBagNormalizer(),
            new NovaContract\ToolCallMessageNormalizer(),
            new NovaContract\ToolNormalizer(),
            new NovaContract\UserMessageNormalizer(),
        );
        $this->modelClients = $modelClients instanceof \Traversable ? iterator_to_array($modelClients) : $modelClients;
    }

    public function request(Model $model, array|string|object $input, array $options = []): ResponseInterface
    {
        $payload = $this->contract->createRequestPayload($model, $input);
        $options = array_merge($model->getOptions(), $options);

        if (isset($options['tools'])) {
            $options['tools'] = $this->contract->createToolOption($options['tools'], $model);
        }

        return $this->doRequest($model, $payload, $options);
    }

    /**
     * @param array<string, mixed>|string $payload
     * @param array<string, mixed>        $options
     */
    private function doRequest(Model $model, array|string $payload, array $options = []): ResponseInterface
    {
        foreach ($this->modelClients as $modelClient) {
            if ($modelClient->supports($model)) {
                return $modelClient->request($model, $payload, $options);
            }
        }

        throw new RuntimeException('No response factory registered for model "'.$model::class.'" with given input.');
    }
}
