<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\VertexAi\Contract;

use Symfony\AI\Platform\Bridge\VertexAi\Gemini\Model;
use Symfony\AI\Platform\Bridge\VertexAi\Gemini\ResultConverter;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Message\AssistantMessage;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Message\Content\Thinking;
use Symfony\AI\Platform\Model as BaseModel;
use Symfony\AI\Platform\Result\ToolCall;

/**
 * @author Junaid Farooq <ulislam.junaid125@gmail.com>
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

        foreach ($data->getContent() as $part) {
            if ($part instanceof Text) {
                $textPart = ['text' => $part->getText()];
                if (null !== $part->getSignature()) {
                    $textPart['thoughtSignature'] = $part->getSignature();
                }
                $normalized[] = $textPart;
                continue;
            }

            if ($part instanceof Thinking) {
                $thoughtPart = ['text' => $part->getContent(), 'thought' => true];
                if (null !== $part->getSignature()) {
                    $thoughtPart['thoughtSignature'] = $part->getSignature();
                }
                $normalized[] = $thoughtPart;
                continue;
            }

            if ($part instanceof ToolCall) {
                $functionCall = [
                    'name' => $part->getName(),
                ];

                if ([] !== $part->getArguments()) {
                    $functionCall['args'] = $part->getArguments();
                }

                $toolPart = ['functionCall' => $functionCall];
                if (null !== $part->getSignature()) {
                    $toolPart['thoughtSignature'] = $part->getSignature();
                }
                $normalized[] = $toolPart;
            }
        }

        return $normalized;
    }

    protected function supportedDataClass(): string
    {
        return AssistantMessage::class;
    }

    protected function supportsModel(BaseModel $model): bool
    {
        return $model instanceof Model;
    }
}
