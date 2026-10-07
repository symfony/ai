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

use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * Thin UI shell over {@see Game}: reads screen state through it, forwards every mutation
 * to it, and owns only the bits that never survive a page reload (the two text inputs and
 * the speech-to-text confirm/edit/re-record flow).
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
#[AsLiveComponent('party_game')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp(writable: true)]
    public ?string $nameInput = null;

    #[LiveProp]
    public ?string $nameError = null;

    #[LiveProp(writable: true)]
    public ?string $answerInput = null;

    /**
     * @var 'cleaning'|'confirm'|null
     */
    #[LiveProp]
    public ?string $voicePhase = null;

    #[LiveProp]
    public ?string $voiceRaw = null;

    #[LiveProp]
    public ?string $voiceText = null;

    #[LiveProp]
    public bool $voiceUnedited = false;

    #[LiveProp]
    public ?string $voiceError = null;

    public function __construct(
        private readonly Game $game,
        private readonly Judge $judge,
        private readonly TranscriptCleaner $cleaner,
    ) {
    }

    public function getScreen(): string
    {
        return $this->game->getScreen();
    }

    /**
     * @return list<string>
     */
    public function getPlayers(): array
    {
        return $this->game->getPlayers();
    }

    public function getTotalRounds(): int
    {
        return $this->game->getTotalRounds();
    }

    public function getRound(): int
    {
        return $this->game->getRound();
    }

    /**
     * @return array{category: string, text: string}|null
     */
    public function getPrompt(): ?array
    {
        return $this->game->getPrompt();
    }

    /**
     * @return list<string>
     */
    public function getOrder(): array
    {
        return $this->game->getOrder();
    }

    public function getTurn(): int
    {
        return $this->game->getTurn();
    }

    public function getCurrentPlayer(): ?string
    {
        return $this->game->getCurrentPlayer();
    }

    /**
     * @return list<array{player: string, text: string}>
     */
    public function getAnswers(): array
    {
        return $this->game->getAnswers();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getVerdicts(): array
    {
        return $this->game->getVerdicts();
    }

    public function getError(): ?string
    {
        return $this->game->getError();
    }

    public function getRevealIndex(): int
    {
        return $this->game->getRevealIndex();
    }

    /**
     * @return array<string, array{convincing: int, creative: int, unhinged: int}>
     */
    public function getTally(): array
    {
        return $this->game->getTally();
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function getHistory(): array
    {
        return $this->game->getHistory();
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getLastAwards(): array
    {
        return $this->game->getLastAwards();
    }

    public function colorFor(string $player): int
    {
        $index = array_search($player, $this->game->getPlayers(), true);

        return (false === $index ? 0 : $index) % 8;
    }

    /**
     * @return list<array{player: string, tally: array{convincing: int, creative: int, unhinged: int}, total: int}>
     */
    public function getTallyRows(): array
    {
        $tally = $this->game->getTally();
        $rows = array_map(
            static fn (string $player): array => ['player' => $player, 'tally' => $tally[$player], 'total' => array_sum($tally[$player])],
            $this->game->getPlayers(),
        );

        usort($rows, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $rows;
    }

    public function isFull(): bool
    {
        return $this->game->isFull();
    }

    public function canStart(): bool
    {
        return $this->game->canStart();
    }

    /**
     * @return list<array{player: string, total: int, tally: array{convincing: int, creative: int, unhinged: int}}>
     */
    public function champions(): array
    {
        return $this->game->champions();
    }

    /**
     * @param array{convincing: int, creative: int, unhinged: int} $tally
     */
    public function titleFor(array $tally): string
    {
        return $this->game->titleFor($tally);
    }

    /**
     * @return list<string>
     */
    public function getCreativityLevels(): array
    {
        return Judge::CREATIVITY_LEVELS;
    }

    /**
     * @return list<string>
     */
    public function getChaosLevels(): array
    {
        return Judge::CHAOS_LEVELS;
    }

    /**
     * @return array<string, array{title: string, icon: string, metric: string}>
     */
    public function getBadges(): array
    {
        return [
            'convincing' => ['title' => 'Most Convincing', 'icon' => '🤝', 'metric' => 'convincing'],
            'creative' => ['title' => 'Most Creative', 'icon' => '💡', 'metric' => 'creativity'],
            'unhinged' => ['title' => 'Most Unhinged', 'icon' => '🌪️', 'metric' => 'chaos'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function getVibeLabels(): array
    {
        return [
            'sincere' => '💖 Sincere vibe',
            'smooth' => '😎 Smooth talker vibe',
            'chaotic' => '🎪 Chaotic vibe',
            'lazy' => '🥱 Low-effort vibe',
        ];
    }

    #[LiveAction]
    public function addPlayer(): void
    {
        $result = $this->game->addPlayer($this->nameInput ?? '');

        if (true === $result) {
            $this->nameInput = null;
            $this->nameError = null;

            return;
        }

        $this->nameError = $result;
    }

    #[LiveAction]
    public function removePlayer(#[LiveArg] string $name): void
    {
        $this->game->removePlayer($name);
    }

    #[LiveAction]
    public function setRounds(#[LiveArg] int $rounds): void
    {
        $this->game->setRounds($rounds);
    }

    #[LiveAction]
    public function startGame(): void
    {
        if ($this->game->canStart()) {
            $this->game->startGame();
        }
    }

    #[LiveAction]
    public function beginPassing(): void
    {
        $this->game->beginPassing();
    }

    #[LiveAction]
    public function imHere(): void
    {
        $this->game->imHere();
    }

    #[LiveAction]
    public function submitAnswer(): void
    {
        $text = $this->answerInput ?? '';
        if ('' === trim($text)) {
            return;
        }

        $this->answerInput = null;
        $this->clearVoice();

        if ($this->game->submitAnswer($text)) {
            $this->judgeNow();
        }
    }

    #[LiveAction]
    public function retryJudging(): void
    {
        $this->judgeNow();
    }

    #[LiveAction]
    public function advanceReveal(): void
    {
        $this->game->advanceReveal();
    }

    #[LiveAction]
    public function nextRound(): void
    {
        $this->game->finishRoundOrContinue(false);
    }

    #[LiveAction]
    public function endNow(): void
    {
        $this->game->finishRoundOrContinue(true);
    }

    #[LiveAction]
    public function playAgain(): void
    {
        $this->game->playAgain();
    }

    #[LiveAction]
    public function newCrew(): void
    {
        $this->game->newCrew();
    }

    #[LiveAction]
    public function reset(): void
    {
        $this->game->reset();
    }

    #[LiveAction]
    public function requestCleanup(#[LiveArg] string $transcript): void
    {
        $this->voiceRaw = $transcript;

        try {
            $this->voiceText = $this->cleaner->cleanup($transcript);
            $this->voiceUnedited = false;
            $this->voiceError = null;
        } catch (\Throwable $e) {
            $this->voiceText = $transcript;
            $this->voiceUnedited = true;
            $this->voiceError = $e->getMessage();
        }

        $this->voicePhase = 'confirm';
    }

    #[LiveAction]
    public function voiceConfirm(): void
    {
        $text = $this->voiceText ?? '';
        $this->clearVoice();

        if ('' !== trim($text) && $this->game->submitAnswer($text)) {
            $this->judgeNow();
        }
    }

    #[LiveAction]
    public function voiceEdit(): void
    {
        $this->answerInput = $this->voiceText;
        $this->clearVoice();
    }

    #[LiveAction]
    public function voiceRerecord(): void
    {
        $this->clearVoice();
    }

    private function judgeNow(): void
    {
        $prompt = $this->game->getPrompt();
        \assert(null !== $prompt);

        try {
            $verdicts = [];
            foreach ($this->game->getAnswers() as $answer) {
                $verdicts[] = ['player' => $answer['player'], 'text' => $answer['text']] + $this->judge->judge($prompt['text'], $answer['text']);
            }
            $this->game->recordVerdicts($verdicts);
        } catch (\Throwable $e) {
            $this->game->recordJudgingError(\sprintf('The judge could not reach a verdict: %s', $e->getMessage()));
        }
    }

    private function clearVoice(): void
    {
        $this->voicePhase = null;
        $this->voiceRaw = null;
        $this->voiceText = null;
        $this->voiceUnedited = false;
        $this->voiceError = null;
    }
}
