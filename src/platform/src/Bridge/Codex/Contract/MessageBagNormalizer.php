<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Codex\Contract;

use Symfony\AI\Platform\Bridge\Codex\Codex;
use Symfony\AI\Platform\Contract;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Exception\MissingModelSupportException;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Message\UserMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Normalizes a MessageBag for the Codex CLI by extracting the system
 * prompt into a dedicated field and the text of the user messages after
 * the last assistant message as the prompt.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class MessageBagNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param MessageBag $data
     *
     * @return array{
     *     prompt: string,
     *     system_prompt?: string,
     * }
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $userMessages = [];
        $lastUserMessage = null;
        foreach ($data->withoutSystemMessage()->getMessages() as $message) {
            if ($message instanceof AssistantMessage) {
                $userMessages = [];
                continue;
            }

            if ($message instanceof UserMessage) {
                $userMessages[] = $message;
                $lastUserMessage = $message;
            }
        }

        // Without a user message after the last assistant message, fall back to the last user message
        $activeMessages = [] !== $userMessages ? $userMessages : (null !== $lastUserMessage ? [$lastUserMessage] : []);

        $model = $context[Contract::CONTEXT_MODEL] ?? null;
        foreach ($activeMessages as $message) {
            if ($message->hasImageContent()) {
                throw MissingModelSupportException::forImageInput($model instanceof Model ? $model : new Codex('codex'));
            }

            if ($message->hasAudioContent()) {
                throw MissingModelSupportException::forAudioInput($model instanceof Model ? $model : new Codex('codex'));
            }
        }

        $texts = [];
        if ([] !== $activeMessages) {
            $messages = $this->normalizer->normalize($activeMessages, $format, $context);
            foreach ($messages as $message) {
                if (\is_string($message['content'])) {
                    $texts[] = $message['content'];
                } elseif (\is_array($message['content'])) {
                    foreach ($message['content'] as $block) {
                        if ('text' === ($block['type'] ?? null)) {
                            $texts[] = $block['text'];
                        }
                    }
                }
            }
        }

        $array = ['prompt' => implode("\n\n", $texts)];

        if (null !== $system = $data->getSystemMessage()) {
            $array['system_prompt'] = (string) $system->getContent();
        }

        return $array;
    }

    protected function supportedDataClass(): string
    {
        return MessageBag::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Codex;
    }
}
