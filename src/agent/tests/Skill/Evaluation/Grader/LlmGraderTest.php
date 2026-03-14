<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Skill\Evaluation\Grader;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\Exception\RuntimeException;
use Symfony\AI\Agent\Skill\Evaluation\Grader\LlmGrader;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\AI\Platform\Result\DeferredResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\ResultConverterInterface;
use Symfony\AI\Platform\TokenUsage\TokenUsageExtractorInterface;

final class LlmGraderTest extends TestCase
{
    public function testGradePassingAssertion()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->with('gpt-4o-mini', $this->isInstanceOf(MessageBag::class))
            ->willReturn($this->createDeferredResult('{"passed": true, "evidence": "Output mentions PHP correctly"}'));

        $grader = new LlmGrader($platform, 'gpt-4o-mini');
        $result = $grader->grade('PHP is a language', ['Output mentions PHP'], 'PHP is a programming language');

        $this->assertCount(1, $result->getAssertionResults());
        $this->assertTrue($result->getAssertionResults()[0]->isPassed());
        $this->assertSame('Output mentions PHP correctly', $result->getAssertionResults()[0]->getEvidence());
    }

    public function testGradeFailingAssertion()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($this->createDeferredResult('{"passed": false, "evidence": "Output does not mention Java"}'));

        $grader = new LlmGrader($platform, 'gpt-4o-mini');
        $result = $grader->grade('PHP is a language', ['Output mentions Java'], 'Java is a programming language');

        $this->assertCount(1, $result->getAssertionResults());
        $this->assertFalse($result->getAssertionResults()[0]->isPassed());
    }

    public function testGradeMultipleAssertions()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->exactly(2))
            ->method('invoke')
            ->willReturnOnConsecutiveCalls(
                $this->createDeferredResult('{"passed": true, "evidence": "Found it"}'),
                $this->createDeferredResult('{"passed": false, "evidence": "Not found"}'),
            );

        $grader = new LlmGrader($platform, 'gpt-4o-mini');
        $result = $grader->grade('output', ['assertion1', 'assertion2'], 'expected');

        $this->assertCount(2, $result->getAssertionResults());
        $summary = $result->getSummary();
        $this->assertSame(1, $summary['passed']);
        $this->assertSame(1, $summary['failed']);
        $this->assertSame(0.5, $summary['pass_rate']);
    }

    public function testGradeThrowsOnMalformedResponse()
    {
        $platform = $this->createMock(PlatformInterface::class);
        $platform->expects($this->once())
            ->method('invoke')
            ->willReturn($this->createDeferredResult('not json at all'));

        $grader = new LlmGrader($platform, 'gpt-4o-mini');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed grading response');

        $grader->grade('output', ['assertion'], 'expected');
    }

    private function createDeferredResult(string $text): DeferredResult
    {
        $rawResult = $this->createMock(RawResultInterface::class);
        $textResult = new TextResult($text);

        $tokenUsageExtractor = $this->createMock(TokenUsageExtractorInterface::class);
        $tokenUsageExtractor->method('extract')->willReturn(null);

        $converter = $this->createMock(ResultConverterInterface::class);
        $converter->method('convert')->willReturn($textResult);
        $converter->method('getTokenUsageExtractor')->willReturn($tokenUsageExtractor);

        return new DeferredResult($converter, $rawResult);
    }
}
