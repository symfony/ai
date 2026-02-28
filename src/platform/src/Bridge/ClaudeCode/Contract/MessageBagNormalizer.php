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
 * prompt into a dedicated field and the last user message as the prompt.
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

        $prompt = '';
        foreach (array_reverse($messages) as $message) {
            if ('user' !== ($message['role'] ?? null)) {
                continue;
            }

            if (\is_string($message['content'])) {
                $prompt = $message['content'];
                break;
            }

            if (\is_array($message['content'])) {
                foreach ($message['content'] as $block) {
                    if ('text' === ($block['type'] ?? null)) {
                        $prompt = $block['text'];
                        break 2;
                    }
                }
            }
        }

        $array = ['prompt' => $prompt];

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
