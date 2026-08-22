<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Contract\Message;

use Symfony\AI\Platform\Bridge\OpenResponses\ResponsesModel;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;

/**
 * @author Guillermo Lengemann <guillermo.lengemann@gmail.com>
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer implements NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    /**
     * @param AssistantMessage $data
     *
     * @return list<array<string, mixed>>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $items = [];
        $text = '';

        // Responses input is a flat list of output items. Text is buffered until
        // a reasoning item or tool call establishes the next position in that list
        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $text .= $part->getText();
                continue;
            }

            if ($part instanceof Thinking) {
                // With store=false, the signature carries the original reasoning output item
                $signature = $part->getSignature();
                if (null === $signature) {
                    continue;
                }

                try {
                    $item = json_decode($signature, true, flags: \JSON_THROW_ON_ERROR);
                } catch (\JsonException) {
                    // Signatures from other providers may be opaque strings
                    continue;
                }

                if (!\is_array($item) || 'reasoning' !== ($item['type'] ?? null)) {
                    continue;
                }

                if ('' !== $text) {
                    $items[] = [
                        'role' => $data->getRole()->value,
                        'type' => 'message',
                        'content' => $text,
                    ];
                    $text = '';
                }
                $items[] = $item;
                continue;
            }

            if ($part instanceof ToolCall) {
                if ('' !== $text) {
                    $items[] = [
                        'role' => $data->getRole()->value,
                        'type' => 'message',
                        'content' => $text,
                    ];
                    $text = '';
                }
                /** @var array<string, mixed> $toolCall */
                $toolCall = $this->normalizer->normalize($part, $format, $context);
                $items[] = $toolCall;
            }
        }

        if ('' !== $text) {
            $items[] = [
                'role' => $data->getRole()->value,
                'type' => 'message',
                'content' => $text,
            ];
        }

        // Keep the existing empty-assistant representation when nothing is replayable
        if ([] === $items) {
            $items[] = [
                'role' => $data->getRole()->value,
                'type' => 'message',
                'content' => null,
            ];
        }

        return $items;
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof ResponsesModel;
    }
}
