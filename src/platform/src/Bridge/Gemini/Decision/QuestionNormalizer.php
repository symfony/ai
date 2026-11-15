<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Decision;

use Symfony\AI\Platform\Bridge\Gemini\Decision;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Normalizes a question into the format of the Gemini Decisions API.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class QuestionNormalizer extends ModelContractNormalizer
{
    /**
     * @param QuestionInterface $data
     *
     * @return array{type: string, instructions: string, criteria?: object|list<string>}
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return match (true) {
            $data instanceof NoulQuestion => $this->normalizeNoulQuestion($data),
            $data instanceof ChoiceQuestion => [
                'type' => 'choice',
                'instructions' => $data->getInstructions(),
                'criteria' => (object) array_map(static fn (?string $description): string => $description ?? '', $data->getOptions()),
            ],
            $data instanceof ScoreQuestion => [
                'type' => 'score',
                'instructions' => $data->getInstructions(),
                'criteria' => $data->getLevels(),
            ],
            default => throw new InvalidArgumentException(\sprintf('Unsupported question type "%s".', $data::class)),
        };
    }

    protected function supportedDataClass(): string
    {
        return QuestionInterface::class;
    }

    protected function supportsModel(Model $model): bool
    {
        return $model instanceof Decision;
    }

    /**
     * @return array{type: 'noul', instructions: string, criteria?: object}
     */
    private function normalizeNoulQuestion(NoulQuestion $question): array
    {
        $normalized = [
            'type' => 'noul',
            'instructions' => $question->getInstructions(),
        ];

        $criteria = array_filter(['true_description' => $question->getTrue(), 'false_description' => $question->getFalse()], static fn (?string $criterion): bool => null !== $criterion);
        if ([] !== $criteria) {
            $normalized['criteria'] = (object) $criteria;
        }

        return $normalized;
    }
}
