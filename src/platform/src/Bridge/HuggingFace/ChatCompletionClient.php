<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\HuggingFace;

use Symfony\AI\Platform\Bridge\HuggingFace\Provider as HfProvider;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\RequestEnvelope;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\TransportInterface;

/**
 * The only HF endpoint that doesn't fit the default `/{provider}/models/{name}`
 * URL pattern. HF's own inference uses `/hf-inference/models/{name}/v1/chat/completions`;
 * third-party providers expose an OpenAI-compatible `/{provider}/v1/chat/completions`
 * endpoint with the model name in the JSON body instead of the URL.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class ChatCompletionClient extends AbstractTaskClient
{
    public const ENDPOINT = 'hf.'.Task::CHAT_COMPLETION;

    public function __construct(
        TransportInterface $transport,
        private readonly string $defaultProvider = HfProvider::HF_INFERENCE,
    ) {
        parent::__construct($transport);
    }

    public function endpoint(): string
    {
        return self::ENDPOINT;
    }

    public function request(Model $model, array|string $payload, array $options = []): RawResultInterface
    {
        $provider = $options['provider'] ?? $this->defaultProvider;
        unset($options['provider']);

        if (HfProvider::HF_INFERENCE === $provider) {
            $path = '/{provider}/models/{name}/v1/chat/completions';
            $body = array_merge($options, \is_array($payload) ? $payload : ['inputs' => $payload]);
        } else {
            // OpenAI-compatible endpoint — model name belongs in the body, not the URL.
            $path = '/{provider}/v1/chat/completions';
            $body = array_merge($options, \is_array($payload) ? $payload : ['inputs' => $payload], ['model' => $model->getName()]);
        }

        $envelope = new RequestEnvelope(payload: $body, path: $path);

        return $this->transport->send($model, $envelope, $options);
    }

    public function convert(RawResultInterface $raw, array $options = []): TextResult
    {
        $data = $raw->getData();

        return new TextResult($data['choices'][0]['message']['content'] ?? '');
    }
}
