<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Decision\Contract;

use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Normalizes a question into the format that TypeSafe, Ollama and OpenRouter share.
 *
 * @author Yoan Bernabeu <yoan.bernabeu@gmail.com>
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
                // A JSON object, even when the options are numeric
                'criteria' => (object) $data->getOptions(),
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
        return $model->supports(Capability::DECISION);
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

        $criteria = array_filter(['true' => $question->getTrue(), 'false' => $question->getFalse()], static fn (?string $criterion): bool => null !== $criterion);
        if ([] !== $criteria) {
            $normalized['criteria'] = (object) $criteria;
        }

        return $normalized;
    }
}
