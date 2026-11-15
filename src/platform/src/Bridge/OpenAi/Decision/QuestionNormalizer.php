<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenAi\Decision;

use Symfony\AI\Platform\Bridge\OpenAi\Gpt;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Contract\Normalizer\ModelContractNormalizer;
use Symfony\AI\Platform\Decision\Question\ChoiceQuestion;
use Symfony\AI\Platform\Decision\Question\NoulQuestion;
use Symfony\AI\Platform\Decision\Question\QuestionInterface;
use Symfony\AI\Platform\Decision\Question\ScoreQuestion;
use Symfony\AI\Platform\Exception\InvalidArgumentException;
use Symfony\AI\Platform\Model;

/**
 * Normalizes a question into the format of the OpenAI Decisions API, without its name.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class QuestionNormalizer extends ModelContractNormalizer
{
    /**
     * @param QuestionInterface $data
     *
     * @return array<string, mixed>
     */
    public function normalize(mixed $data, ?string $format = null, array $context = []): array
    {
        return match (true) {
            $data instanceof NoulQuestion => [
                'type' => 'predicate',
                'instructions' => $this->getPredicateInstructions($data),
            ],
            $data instanceof ChoiceQuestion => [
                'type' => 'choice',
                'instructions' => $data->getInstructions(),
                'choices' => array_map(
                    static fn (string|int $value, ?string $description): array => null === $description ? ['value' => (string) $value] : ['value' => (string) $value, 'description' => $description],
                    array_keys($data->getOptions()),
                    $data->getOptions(),
                ),
            ],
            $data instanceof ScoreQuestion => [
                'type' => 'score',
                'instructions' => $data->getInstructions(),
                'levels' => array_map(static fn (string $level): array => ['label' => $level], $data->getLevels()),
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
        return $model instanceof Gpt && $model->supports(Capability::DECISION);
    }

    private function getPredicateInstructions(NoulQuestion $question): string
    {
        // Predicates have no criteria, so they become part of the instructions
        $instructions = $question->getInstructions();

        if (null !== $true = $question->getTrue()) {
            $instructions .= "\nTrue means: ".$true;
        }

        if (null !== $false = $question->getFalse()) {
            $instructions .= "\nFalse means: ".$false;
        }

        return $instructions;
    }
}
