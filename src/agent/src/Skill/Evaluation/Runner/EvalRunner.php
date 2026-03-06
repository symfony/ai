<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Skill\Evaluation\Runner;

use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\EvalRunResult;
use Symfony\AI\Agent\Skill\Evaluation\TimingResult;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\TokenUsage\TokenUsageInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Clock\MonotonicClock;

/**
 * Runs a single eval case against an agent, measuring timing and token usage.
 *
 * @author Guillaume Loulier <contact@guillaumeloulier.fr>
 */
final class EvalRunner implements EvalRunnerInterface
{
    public function __construct(
        private readonly AgentInterface $agent,
        private readonly ClockInterface $clock = new MonotonicClock(),
    ) {
    }

    public function run(EvalCase $evalCase): EvalRunResult
    {
        $messages = new MessageBag(
            Message::ofUser($evalCase->getPrompt()),
        );

        $startTime = $this->clock->now();
        $result = $this->agent->call($messages);
        $endTime = $this->clock->now();

        $durationMs = (int) (($endTime->getTimestamp() - $startTime->getTimestamp()) * 1000
            + ($endTime->format('u') - $startTime->format('u')) / 1000);

        $totalTokens = 0;
        $tokenUsage = $result->getMetadata()->get('token_usage');
        if ($tokenUsage instanceof TokenUsageInterface) {
            $totalTokens = $tokenUsage->getTotalTokens() ?? 0;
        }

        $content = $result->getContent();
        $output = \is_string($content) ? $content : (string) $content;

        return new EvalRunResult(
            $evalCase,
            $output,
            new TimingResult($totalTokens, $durationMs),
        );
    }
}
