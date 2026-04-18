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
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ToolCallResult;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 *
 * @phpstan-import-type Part from ResultConverter
 */
final class AssistantMessageNormalizer extends ModelContractNormalizer
{
    /**
     * @param AssistantMessage $data
     *
     * @return list<Part>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        $normalized = [];

        $parts = $data->getContent();
        if (!$parts instanceof MultiPartResult) {
            $parts = [$parts];
        }

        foreach ($parts as $i => $content) {
            if ($content instanceof TextResult) {
                $normalized[$i]['text'] = $content->getContent();
            }

            if ($content instanceof ToolCallResult) {
                $toolCall = $content->getContent()[0];
                $normalized[$i]['functionCall'] = [
                    'id' => $toolCall->getId(),
                    'name' => $toolCall->getName(),
                ];

                if ($toolCall->getArguments()) {
                    $normalized[$i]['functionCall']['args'] = $toolCall->getArguments();
                }
            }
        }

        return array_values($normalized);
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
