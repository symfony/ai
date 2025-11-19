<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Gemini\CodeExecution;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Gemini\Gemini\ResultConverter;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testItReturnsAggregatedTextOnSuccess()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/Gemini/code_execution_outcome_ok.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $result = $converter->convert(new RawHttpResult($response));
        $this->assertInstanceOf(TextResult::class, $result);

        $this->assertEquals("Second text\nThird text\nFourth text", $result->getContent());
    }

    public function testItThrowsExceptionOnFailure()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/Gemini/code_execution_outcome_failed.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $this->expectException(\RuntimeException::class);
        $converter->convert(new RawHttpResult($response));
    }

    public function testItThrowsExceptionOnTimeout()
    {
        $response = $this->createStub(ResponseInterface::class);
        $responseContent = file_get_contents(\dirname(__DIR__, 6).'/fixtures/Bridge/Gemini/code_execution_outcome_deadline_exceeded.json');

        $response
            ->method('toArray')
            ->willReturn(json_decode($responseContent, true));

        $converter = new ResultConverter();

        $this->expectException(\RuntimeException::class);
        $converter->convert(new RawHttpResult($response));
    }
}
