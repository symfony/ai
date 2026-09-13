<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Cerebras\Contract;

use Symfony\AI\Platform\Contract\Normalizer\Message\AssistantMessageNormalizer as BaseAssistantMessageNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Drops the `reasoning_content` of the shared contract, which Cerebras answers with a 400
 * "property 'messages.N.assistant.reasoning_content' is unsupported".
 *
 * Its streamed responses carry a reasoning trace, so a replayed turn reaches this. Cerebras has no
 * shape of its own to put it in - a Mistral-style thinking chunk is rejected too - so the trace is
 * dropped and the turn replays as text and tool calls.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    private BaseAssistantMessageNormalizer $inner;

    public function __construct()
    {
        $this->inner = new BaseAssistantMessageNormalizer();
    }

    public function setNormalizer(NormalizerInterface $normalizer): void
    {
        $this->inner->setNormalizer($normalizer);
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof AssistantMessage;
    }

    public function getSupportedTypes(?string $format): array
    {
        return [
            AssistantMessage::class => true,
        ];
    }

    /**
     * @param AssistantMessage $data
     *
     * @return array{role: 'assistant', content: string|null, tool_calls?: array<array<string, mixed>>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $array = $this->inner->normalize($data, $format, $context);

        unset($array['reasoning_content']);

        return $array;
    }
}
