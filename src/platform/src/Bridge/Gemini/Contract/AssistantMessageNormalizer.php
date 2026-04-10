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
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\ToolCall;
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
     * @return array{text?: string, functionCall?: array<string, mixed>}[]
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        foreach ($data as $i => $content) {
            if ($content instanceof Text) {
                $normalized[$i]['text'] = $content->getText();
            }

            if ($content instanceof ToolCall) {
                $normalized[$i]['functionCall'] = [
                    'id' => $content->getId(),
                    'name' => $content->getName(),
                ];

                if ($content->getArguments()) {
                    $normalized[$i]['functionCall']['args'] = $content->getArguments();
                }
            }
        }

        return $normalized;
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
