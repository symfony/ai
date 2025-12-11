<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Anthropic;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Anthropic\ResultConverter;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;
use Symfony\Component\HttpClient\Response\MockResponse;

final class ResultConverterTest extends TestCase
{
    public function testConvertThrowsExceptionWhenContentIsToolUseAndLacksText()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse([
            'content' => [
                [
                    'type' => 'tool_use',
                    'id' => 'toolu_01UM4PcTjC1UDiorSXVHSVFM',
                    'name' => 'xxx_tool',
                    'input' => ['action' => 'get_data'],
                ],
            ],
        ]));
        $httpResponse = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $handler = new ResultConverter();

        $result = $handler->convert(new RawHttpResult($httpResponse));
        $this->assertInstanceOf(ToolCallResult::class, $result);
        $this->assertCount(1, $result->getContent());
        $this->assertSame('toolu_01UM4PcTjC1UDiorSXVHSVFM', $result->getContent()[0]->getId());
        $this->assertSame('xxx_tool', $result->getContent()[0]->getName());
        $this->assertSame(['action' => 'get_data'], $result->getContent()[0]->getArguments());
    }

    public function testModelNotFoundError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error","error":{"type":"not_found_error","message":"model: claude-3-5-sonnet-20241022"}}'),
        ]);

        $response = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [not_found_error]: "model: claude-3-5-sonnet-20241022"');

        $converter->convert(new RawHttpResult($response));
    }

    public function testUnknownError()
    {
        $httpClient = new MockHttpClient([
            new MockResponse('{"type":"error"}'),
        ]);

        $response = $httpClient->request('POST', 'https://api.anthropic.com/v1/messages');
        $converter = new ResultConverter();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('API Error [Unknown]: "An unknown error occurred."');

        $converter->convert(new RawHttpResult($response));
    }
}
