<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Contract;

use Symfony\AI\Platform\Bridge\Gemini\Gemini;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param AssistantMessage $data
     *
     * @return array{array{text: string}}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        $content = $data->getContent();
        if (null !== $content) {
            if (\is_string($content)) {
                $normalized['text'] = $content;
            } elseif ($content instanceof \Stringable) {
                $normalized['text'] = (string) $content;
            } else {
                $normalized['text'] = json_encode(
                    $this->normalizer->normalize($content, $format, $context),
                    \JSON_THROW_ON_ERROR
                );
            }
        }

        if ($data->hasToolCalls()) {
            $normalized['functionCall'] = [
                'id' => $data->getToolCalls()[0]->getId(),
                'name' => $data->getToolCalls()[0]->getName(),
            ];

            if ($data->getToolCalls()[0]->getArguments()) {
                $normalized['functionCall']['args'] = $data->getToolCalls()[0]->getArguments();
            }
        }

        return [$normalized];
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Gemini;
    }
}
