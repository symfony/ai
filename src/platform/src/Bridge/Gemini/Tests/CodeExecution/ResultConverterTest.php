<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Gemini\Tests\CodeExecution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItReturnsAggregatedTextOnSuccess()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_ok.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult("First text\n"),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(true, 'Hello, World!'),
            new TextResult("Second text\n"),
            new TextResult("Third text\n"),
            new TextResult('Fourth text'),
        ];

        $this->assertEquals($parts, $result->getContent());
        $this->assertEquals("First text\nSecond text\nThird text\nFourth text", $result->asText());
    }

    public function testItDoesNotSucceedOnFailure()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_failed.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult('First text'),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(false, 'An error occurred during code execution.'),
            new TextResult('Last text'),
        ];

        $this->assertEquals($parts, $result->getContent());
    }

    public function testItDoesNotSucceedOnTimeout()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(__DIR__.'/Fixtures/code_execution_outcome_deadline_exceeded.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(MultiPartResult::class, $result);

        $parts = [
            new TextResult('First text'),
            new ExecutableCodeResult("print('Hello, World!')", 'PYTHON'),
            new CodeExecutionResult(false, 'An error occurred during code execution.'),
            new TextResult('Last text'),
        ];

        $this->assertEquals($parts, $result->getContent());
    }
}
