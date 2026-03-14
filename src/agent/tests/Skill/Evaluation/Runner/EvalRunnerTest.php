<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation\Runner;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\AgentInterface;
use Symfony\AI\Agent\Skill\Evaluation\EvalCase;
use Symfony\AI\Agent\Skill\Evaluation\Runner\EvalRunner;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Component\Clock\MockClock;

final class EvalRunnerTest extends TestCase
{
    public function testRunSendsPromptAndCapturesTiming()
    {
        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Agent response text');

        $metadata = new Metadata();
        $result->method('getMetadata')->willReturn($metadata);

        $agent = $this->createMock(AgentInterface::class);
        $agent->expects($this->once())
            ->method('call')
            ->with($this->callback(static function (MessageBag $bag): bool {
                $userMessage = $bag->getUserMessage();

                return null !== $userMessage;
            }))
            ->willReturn($result);

        $clock = new MockClock('2026-01-01 10:00:00');

        $runner = new EvalRunner($agent, $clock);
        $evalCase = new EvalCase(1, 'What is PHP?', 'A language');

        $runResult = $runner->run($evalCase);

        $this->assertSame($evalCase, $runResult->getEvalCase());
        $this->assertSame('Agent response text', $runResult->getOutput());
        $this->assertSame(0, $runResult->getTiming()->getTotalTokens());
        $this->assertNull($runResult->getGrading());
    }

    public function testRunExtractsTokenUsage()
    {
        $tokenUsage = new TokenUsage(promptTokens: 50, completionTokens: 100, totalTokens: 150);

        $metadata = new Metadata();
        $metadata->add('token_usage', $tokenUsage);

        $result = $this->createMock(ResultInterface::class);
        $result->method('getContent')->willReturn('Response');
        $result->method('getMetadata')->willReturn($metadata);

        $agent = $this->createMock(AgentInterface::class);
        $agent->method('call')->willReturn($result);

        $clock = new MockClock('2026-01-01 10:00:00');

        $runner = new EvalRunner($agent, $clock);
        $runResult = $runner->run(new EvalCase(1, 'prompt', 'expected'));

        $this->assertSame(150, $runResult->getTiming()->getTotalTokens());
    }
}
