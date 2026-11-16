<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\ClaudeCode\Contract;

use Symfony\AI\Platform\Bridge\ClaudeCode\ClaudeCode;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * Normalizes a MessageBag for the Claude Code CLI by extracting the system
 * prompt into a dedicated field and the text of the user messages after
 * the last assistant message as the prompt.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
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
        $messages = $this->normalizer->normalize($data->withoutSystemMessage()->getMessages(), $format, $context);

        $texts = [];
        $lastUserTexts = [];
        foreach ($messages as $message) {
            $role = $message['role'] ?? null;

            if ('assistant' === $role) {
                $texts = [];
                continue;
            }

            if ('user' !== $role) {
                continue;
            }

            $lastUserTexts = [];
            if (\is_string($message['content'])) {
                $lastUserTexts[] = $message['content'];
            } elseif (\is_array($message['content'])) {
                foreach ($message['content'] as $block) {
                    if ('text' === ($block['type'] ?? null)) {
                        $lastUserTexts[] = $block['text'];
                    }
                }
            }

            array_push($texts, ...$lastUserTexts);
        }

        // Without a user message after the last assistant message, fall back to the last user message
        $array = ['prompt' => implode("\n\n", [] !== $texts ? $texts : $lastUserTexts)];

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
        return $model instanceof ClaudeCode;
    }
}
