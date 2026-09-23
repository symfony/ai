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

use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Session-backed state machine for one pass-and-play game.
 *
 * Mirrors the screen names of the Twig component template: setup, round-intro, handoff,
 * input, judging, reveal, round-summary, final. Judging itself is deliberately not done
 * here, {@see TwigComponent} owns the {@see Judge} call and reports the outcome back
 * through {@see recordVerdicts()} or {@see recordJudgingError()}, so this class stays a
 * plain state holder.
 *
 * @phpstan-type Answer array{player: string, text: string}
 * @phpstan-type Verdict array{
 *     player: string,
 *     text: string,
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
 * @phpstan-type Prompt array{category: string, text: string}
 * @phpstan-type Tally array{convincing: int, creative: int, unhinged: int}
 * @phpstan-type Award array{winners: list<Verdict>, value: float}
 * @phpstan-type RoundHistory array{round: int, prompt: Prompt, verdicts: list<Verdict>, awards: array<string, Award>}
 * @phpstan-type State array{
 *     screen: string,
 *     players: list<string>,
 *     totalRounds: int,
 *     round: int,
 *     deck: list<Prompt>,
 *     prompt: ?Prompt,
 *     order: list<string>,
 *     turn: int,
 *     answers: list<Answer>,
 *     verdicts: list<Verdict>,
 *     error: ?string,
 *     revealIndex: int,
 *     tally: array<string, Tally>,
 *     history: list<RoundHistory>,
 *     lastAwards: array<string, Award>,
 * }
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class Game
{
    private const SESSION_KEY = 'party-game';
    private const MAX_PLAYERS = 8;

    /**
     * @var array{convincing: string, creative: string, unhinged: string}
     */
    private const BADGE_METRICS = [
        'convincing' => 'convincing',
        'creative' => 'creativity',
        'unhinged' => 'chaos',
    ];

    private const TITLES = [
        'convincing' => 'Silver-Tongued Legend',
        'creative' => 'Chief Imagination Officer',
        'unhinged' => 'Master of Absurdity',
        'balanced' => 'Undisputed Jury Favourite',
    ];

    /**
     * @var State|null
     */
    private ?array $state = null;

    public function __construct(
        private readonly RequestStack $requestStack,
    ) {
    }

    public function getScreen(): string
    {
        return $this->state()['screen'];
    }

    /**
     * @return list<string>
     */
    public function getPlayers(): array
    {
        return $this->state()['players'];
    }

    public function getTotalRounds(): int
    {
        return $this->state()['totalRounds'];
    }

    public function getRound(): int
    {
        return $this->state()['round'];
    }

    /**
     * @return Prompt|null
     */
    public function getPrompt(): ?array
    {
        return $this->state()['prompt'];
    }

    /**
     * @return list<string>
     */
    public function getOrder(): array
    {
        return $this->state()['order'];
    }

    public function getTurn(): int
    {
        return $this->state()['turn'];
    }

    public function getCurrentPlayer(): ?string
    {
        return $this->state()['order'][$this->state()['turn']] ?? null;
    }

    /**
     * @return list<Answer>
     */
    public function getAnswers(): array
    {
        return $this->state()['answers'];
    }

    /**
     * @return list<Verdict>
     */
    public function getVerdicts(): array
    {
        return $this->state()['verdicts'];
    }

    public function getError(): ?string
    {
        return $this->state()['error'];
    }

    public function getRevealIndex(): int
    {
        return $this->state()['revealIndex'];
    }

    /**
     * @return array<string, Tally>
     */
    public function getTally(): array
    {
        return $this->state()['tally'];
    }

    /**
     * @return list<RoundHistory>
     */
    public function getHistory(): array
    {
        return $this->state()['history'];
    }

    /**
     * @return array<string, Award>
     */
    public function getLastAwards(): array
    {
        return $this->state()['lastAwards'];
    }

    public function isFull(): bool
    {
        return \count($this->state()['players']) >= self::MAX_PLAYERS;
    }

    public function canStart(): bool
    {
        return \count($this->state()['players']) >= 2;
    }

    /**
     * @return true|string true on success, an error message otherwise
     */
    public function addPlayer(string $name): bool|string
    {
        $state = $this->state();
        $name = trim(preg_replace('/\s+/', ' ', $name) ?? $name);

        if ('' === $name) {
            return 'Type a name first.';
        }
        if (\count($state['players']) >= self::MAX_PLAYERS) {
            return 'Table is full (8).';
        }
        foreach ($state['players'] as $player) {
            if (0 === strcasecmp($player, $name)) {
                return \sprintf('%s is already in. Another name?', $name);
            }
        }

        $state['players'][] = $name;
        $this->save($state);

        return true;
    }

    public function removePlayer(string $name): void
    {
        $state = $this->state();
        $state['players'] = array_values(array_filter($state['players'], static fn (string $player): bool => $player !== $name));
        $this->save($state);
    }

    public function setRounds(int $rounds): void
    {
        $state = $this->state();
        $state['totalRounds'] = $rounds;
        $this->save($state);
    }

    public function startGame(): void
    {
        $state = $this->state();
        $state['round'] = 0;
        $state['deck'] = [];
        $state['tally'] = array_fill_keys($state['players'], ['convincing' => 0, 'creative' => 0, 'unhinged' => 0]);
        $state['history'] = [];
        $this->save($state);

        $this->startRound();
    }

    public function startRound(): void
    {
        $state = $this->state();

        if ([] === $state['deck']) {
            $state['deck'] = self::shuffle(PromptBank::all());
        }
        $state['prompt'] = array_pop($state['deck']);

        ++$state['round'];
        $offset = ($state['round'] - 1) % \count($state['players']);
        $state['order'] = array_merge(\array_slice($state['players'], $offset), \array_slice($state['players'], 0, $offset));
        $state['turn'] = 0;
        $state['answers'] = [];
        $state['verdicts'] = [];
        $state['error'] = null;
        $state['revealIndex'] = 0;
        $state['screen'] = 'round-intro';

        $this->save($state);
    }

    public function beginPassing(): void
    {
        $this->setScreen('handoff');
    }

    public function imHere(): void
    {
        $this->setScreen('input');
    }

    /**
     * @return bool true once every player has answered and judging should start
     */
    public function submitAnswer(string $text): bool
    {
        $state = $this->state();

        $clean = mb_substr(trim($text), 0, 600);
        if ('' === $clean) {
            return false;
        }

        $state['answers'][] = ['player' => $state['order'][$state['turn']], 'text' => $clean];
        ++$state['turn'];

        $readyToJudge = $state['turn'] >= \count($state['order']);
        $state['screen'] = $readyToJudge ? 'judging' : 'handoff';
        $this->save($state);

        return $readyToJudge;
    }

    /**
     * @param list<Verdict> $verdicts
     */
    public function recordVerdicts(array $verdicts): void
    {
        $state = $this->state();
        $state['verdicts'] = $verdicts;
        $state['revealIndex'] = 0;
        $state['error'] = null;
        $state['screen'] = 'reveal';
        $this->save($state);
    }

    public function recordJudgingError(string $message): void
    {
        $state = $this->state();
        $state['error'] = $message;
        $state['screen'] = 'judging';
        $this->save($state);
    }

    public function advanceReveal(): void
    {
        $state = $this->state();

        if ($state['revealIndex'] < \count($state['verdicts']) - 1) {
            ++$state['revealIndex'];
            $this->save($state);

            return;
        }

        $this->save($state);
        $this->awardBadges();
        $state = $this->state();
        $state['screen'] = 'round-summary';
        $this->save($state);
    }

    public function finishRoundOrContinue(bool $endNow = false): void
    {
        $state = $this->state();

        if ($endNow || $state['round'] >= $state['totalRounds']) {
            $state['screen'] = 'final';
            $this->save($state);

            return;
        }

        $this->startRound();
    }

    public function playAgain(): void
    {
        $this->startGame();
    }

    public function newCrew(): void
    {
        $this->save(self::defaultState());
    }

    public function reset(): void
    {
        $this->newCrew();
    }

    /**
     * @return list<array{player: string, total: int, tally: Tally}>
     */
    public function champions(): array
    {
        $tally = $this->getTally();
        $totals = array_map(
            static fn (string $player): array => ['player' => $player, 'total' => array_sum($tally[$player]), 'tally' => $tally[$player]],
            $this->getPlayers(),
        );

        if ([] === $totals) {
            return [];
        }

        $best = max(array_column($totals, 'total'));

        return array_values(array_filter($totals, static fn (array $entry): bool => $entry['total'] === $best));
    }

    /**
     * @param Tally $tally
     */
    public function titleFor(array $tally): string
    {
        $entries = $tally;
        arsort($entries);
        $values = array_values($entries);
        $keys = array_keys($entries);

        if (0 === $values[0]) {
            return 'Participation Trophy Holder';
        }
        if ($values[0] === $values[1]) {
            return self::TITLES['balanced'];
        }

        return self::TITLES[$keys[0]];
    }

    private function awardBadges(): void
    {
        $state = $this->state();
        $verdicts = $state['verdicts'];
        \assert([] !== $verdicts && null !== $state['prompt']);
        $awards = [];

        foreach (self::BADGE_METRICS as $badgeKey => $metric) {
            $best = max(array_column($verdicts, $metric));
            $tied = array_values(array_filter($verdicts, static fn (array $v) => $v[$metric] === $best));
            $winners = \count($tied) === \count($verdicts) ? [] : $tied;
            $awards[$badgeKey] = ['winners' => $winners, 'value' => $best];

            foreach ($winners as $winner) {
                $playerTally = $state['tally'][$winner['player']];
                ++$playerTally[$badgeKey];
                $state['tally'][$winner['player']] = $playerTally;
            }
        }

        $state['history'][] = [
            'round' => $state['round'],
            'prompt' => $state['prompt'],
            'verdicts' => $verdicts,
            'awards' => $awards,
        ];
        $state['lastAwards'] = $awards;

        $this->save($state);
    }

    private function setScreen(string $screen): void
    {
        $state = $this->state();
        $state['screen'] = $screen;
        $this->save($state);
    }

    /**
     * @return State
     */
    private function state(): array
    {
        if (null !== $this->state) {
            return $this->state;
        }

        /** @var State $state */
        $state = $this->requestStack->getSession()->get(self::SESSION_KEY, self::defaultState());
        $this->state = $state;

        return $state;
    }

    /**
     * @param State $state
     */
    private function save(array $state): void
    {
        $this->state = $state;
        $this->requestStack->getSession()->set(self::SESSION_KEY, $state);
    }

    /**
     * @return State
     */
    private static function defaultState(): array
    {
        return [
            'screen' => 'setup',
            'players' => [],
            'totalRounds' => 5,
            'round' => 0,
            'deck' => [],
            'prompt' => null,
            'order' => [],
            'turn' => 0,
            'answers' => [],
            'verdicts' => [],
            'error' => null,
            'revealIndex' => 0,
            'tally' => [],
            'history' => [],
            'lastAwards' => [],
        ];
    }

    /**
     * @template T
     *
     * @param list<T> $list
     *
     * @return list<T>
     */
    private static function shuffle(array $list): array
    {
        shuffle($list);

        return $list;
    }
}
