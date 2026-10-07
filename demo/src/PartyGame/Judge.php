<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\PartyGame;

use Symfony\AI\Platform\Bridge\TypeSafe\Answer\Answers;
use Symfony\AI\Platform\Bridge\TypeSafe\Evaluation;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ChoiceQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\NoulQuestion;
use Symfony\AI\Platform\Bridge\TypeSafe\Question\ScoreQuestion;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Scores one player response with a single multi-question evaluation call.
 *
 * The scenario travels together with the response in the state, otherwise the
 * model has nothing to judge "convincing" against.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Judge
{
    public const CREATIVITY_LEVELS = ['Boring', 'Decent', 'Clever', 'Genius'];
    public const CHAOS_LEVELS = ['Tame', 'Quirky', 'Wild', 'Unhinged'];

    /**
     * @param non-empty-string $model
     */
    public function __construct(
        #[Autowire(service: 'ai.platform.typesafe')]
        private readonly PlatformInterface $platform,
        private readonly string $model = 'jev-latest',
    ) {
    }

    /**
     * @return array{
     *     convincing: float,
     *     verdict: string,
     *     creativity: float,
     *     creativityLevel: string,
     *     creativityProbabilities: list<float>,
     *     chaos: float,
     *     chaosLevel: string,
     *     chaosProbabilities: list<float>,
     *     vibe: string,
     * }
     */
    public function judge(string $scenario, string $response): array
    {
        $evaluation = new Evaluation(
            ['scenario' => $scenario, 'players_response' => $response],
            [
                'convincing' => new NoulQuestion(
                    'Would a fair but skeptical judge accept the players_response as a believable, convincing fulfillment of the scenario?',
                    'Plausible, coherent, and actually addresses the scenario with real persuasive effort',
                    'Implausible, off-topic, lazy, empty, or clearly not trying',
                ),
                'creativity' => new ScoreQuestion(
                    'How creative and original is the players_response for this scenario?',
                    self::CREATIVITY_LEVELS,
                ),
                'chaos' => new ScoreQuestion(
                    'How absurd, chaotic or unhinged is the players_response?',
                    self::CHAOS_LEVELS,
                ),
                'vibe' => new ChoiceQuestion('What is the dominant vibe of the players_response?', [
                    'sincere' => 'Earnest, honest, heartfelt',
                    'smooth' => 'Charming, salesy, silver-tongued',
                    'chaotic' => 'Absurd, surreal, nonsensical',
                    'lazy' => 'Low effort, dismissive, minimal',
                ]),
            ],
        );

        $answers = $this->platform->invoke($this->model, $evaluation)->asObject();
        \assert($answers instanceof Answers);

        $convincing = $answers->getNoul('convincing');
        $creativity = $answers->getScore('creativity');
        $chaos = $answers->getScore('chaos');
        $vibe = $answers->getChoice('vibe');

        return [
            'convincing' => round($convincing->getProbability(), 3),
            'verdict' => self::verdict($convincing->getProbability()),
            'creativity' => round($creativity->getScore(), 3),
            'creativityLevel' => self::level($creativity->getScore(), self::CREATIVITY_LEVELS),
            'creativityProbabilities' => array_values($creativity->getProbabilities()),
            'chaos' => round($chaos->getScore(), 3),
            'chaosLevel' => self::level($chaos->getScore(), self::CHAOS_LEVELS),
            'chaosProbabilities' => array_values($chaos->getProbabilities()),
            'vibe' => $vibe->getChoice(),
        ];
    }

    private static function verdict(float $probability): string
    {
        return match (true) {
            $probability >= 0.85 => 'SOLD!',
            $probability >= 0.6 => 'Buying it',
            $probability >= 0.4 => 'Hmm... maybe',
            $probability >= 0.15 => 'Not buying it',
            default => 'Absolutely not',
        };
    }

    /**
     * @param list<string> $levels
     */
    private static function level(float $score, array $levels): string
    {
        $index = max(0, min(\count($levels) - 1, (int) round($score)));

        return $levels[$index];
    }
}
