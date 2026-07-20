<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\OpenResponses\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\OpenResponses\ResultConverter;
use Symfony\AI\Platform\Exception\AuthenticationException;
use Symfony\AI\Platform\Exception\BadRequestException;
use Symfony\AI\Platform\Exception\ContentFilterException;
use Symfony\AI\Platform\Exception\ExceedContextSizeException;
use Symfony\AI\Platform\Exception\IncompleteStreamException;
use Symfony\AI\Platform\Exception\MaxOutputTokensException;
use Symfony\AI\Platform\Exception\RateLimitExceededException;
use Symfony\AI\Platform\Exception\RuntimeException;
use Symfony\AI\Platform\Exception\ServerException;
use Symfony\AI\Platform\FinishReason\FinishReason;
use Symfony\AI\Platform\FinishReason\FinishReasonCase;
use Symfony\AI\Platform\Result\BinaryResult;
use Symfony\AI\Platform\Result\CodeExecutionResult;
use Symfony\AI\Platform\Result\ComputerCallResult;
use Symfony\AI\Platform\Result\ExecutableCodeResult;
use Symfony\AI\Platform\Result\FileSearchResult;
use Symfony\AI\Platform\Result\InMemoryRawResult;
use Symfony\AI\Platform\Result\LocalShellCallResult;
use Symfony\AI\Platform\Result\McpApprovalRequestResult;
use Symfony\AI\Platform\Result\McpCallResult;
use Symfony\AI\Platform\Result\McpListToolsResult;
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\RawHttpResult;
use Symfony\AI\Platform\Result\RawResultInterface;
use Symfony\AI\Platform\Result\Stream\Delta\MetadataDelta;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingComplete;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingStart;
use Symfony\AI\Platform\Result\Stream\Delta\ToolCallComplete;
use Symfony\AI\Platform\Result\StreamResult;
use Symfony\AI\Platform\Result\TextResult;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\AI\Platform\Result\WebSearchResult;
use Symfony\AI\Platform\TokenUsage\TokenUsage;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class ResultConverterTest extends TestCase
{
    public function testConvertTextResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'message',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Hello world',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Hello world', $result->getContent());
    }

    public function testConvertToolCallResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'function_call',
                    'id' => 'call_123',
                    'name' => 'test_function',
                    'arguments' => '{"arg1": "value1"}',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_123', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertToolCallResultUsesCallIdWhenIdIsMissing()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'function_call',
                    'id' => null,
                    'call_id' => 'call_789',
                    'name' => 'test_function',
                    'arguments' => '{"arg1": "value1"}',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ToolCallResult::class, $result);
        $toolCalls = $result->getContent();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_789', $toolCalls[0]->getId());
        $this->assertSame('test_function', $toolCalls[0]->getName());
        $this->assertSame(['arg1' => 'value1'], $toolCalls[0]->getArguments());
    }

    public function testConvertMultipleMessagesIntoMultiPartResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'role' => 'assistant',
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Part 1',
                    ]],
                ],
                [
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'Part 2',
                    ]],
                    'type' => 'message',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $output = $result->getContent();
        $this->assertCount(2, $output);
        $this->assertSame('Part 1', $output[0]->getContent());
        $this->assertSame('Part 2', $output[1]->getContent());
    }

    public function testConvertReasoningPlusMessageIntoMultiPartResult()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [
                        ['type' => 'summary_text', 'text' => 'Let me work through this.'],
                    ],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => '{"answer": 42}',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('Let me work through this.', $parts[0]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('{"answer": 42}', $parts[1]->getContent());
    }

    public function testConvertReasoningEmitsOneThinkingResultPerSummaryChunk()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [
                        ['type' => 'summary_text', 'text' => 'First, I subtract 7.'],
                        ['type' => 'summary_text', 'text' => 'Then I divide by 8.'],
                    ],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'x = -3.75',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(3, $parts);
        $this->assertInstanceOf(ThinkingResult::class, $parts[0]);
        $this->assertSame('First, I subtract 7.', $parts[0]->getContent());
        $this->assertInstanceOf(ThinkingResult::class, $parts[1]);
        $this->assertSame('Then I divide by 8.', $parts[1]->getContent());
        $this->assertInstanceOf(TextResult::class, $parts[2]);
        $this->assertSame('x = -3.75', $parts[2]->getContent());
    }

    public function testConvertReasoningWithoutSummaryIsDropped()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'final',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('final', $result->getContent());
    }

    public function testConvertWebSearchCallIntoTypedResultAlongsideMessage()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'web_search_call',
                    'id' => 'ws_1',
                    'status' => 'completed',
                    'action' => [
                        'type' => 'search',
                        'query' => 'latest AI news',
                        'queries' => ['latest AI news', 'AI industry developments today'],
                    ],
                ],
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => 'The answer is 42.',
                    ]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertCount(2, $parts);
        $this->assertInstanceOf(WebSearchResult::class, $parts[0]);
        $this->assertSame('latest AI news', $parts[0]->getQuery());
        $this->assertSame('ws_1', $parts[0]->getId());
        $this->assertSame('completed', $parts[0]->getStatus());
        $this->assertInstanceOf(TextResult::class, $parts[1]);
        $this->assertSame('The answer is 42.', $result->asText());
        $this->assertSame(['latest AI news', 'AI industry developments today'], $parts[0]->getQueries());
    }

    public function testConvertWebSearchCallReadsQuery()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'web_search_call',
                    'id' => 'ws_2',
                    'status' => 'completed',
                    'action' => ['type' => 'search', 'query' => 'first query', 'queries' => ['second query', 'third query']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(WebSearchResult::class, $result);
        $this->assertSame('first query', $result->getQuery());
    }

    public function testConvertWebSearchCallReadsFallbackQueryFromQueriesList()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'web_search_call',
                    'id' => 'ws_2',
                    'status' => 'completed',
                    'action' => ['type' => 'search', 'queries' => ['first query', 'second query']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(WebSearchResult::class, $result);
        $this->assertSame('first query', $result->getQuery());
    }

    public function testConvertFileSearchCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'file_search_call',
                    'id' => 'fs_1',
                    'status' => 'completed',
                    'queries' => ['What is deep research?'],
                    'results' => [['file_id' => 'file-1', 'filename' => 'doc.pdf', 'text' => 'lorem', 'score' => 0.9]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(FileSearchResult::class, $result);
        $this->assertSame(['What is deep research?'], $result->getQueries());
        $this->assertSame([['file_id' => 'file-1', 'filename' => 'doc.pdf', 'text' => 'lorem', 'score' => 0.9]], $result->getContent());
        $this->assertSame('fs_1', $result->getId());
        $this->assertSame('completed', $result->getStatus());
    }

    public function testConvertCodeInterpreterCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'code_interpreter_call',
                    'id' => 'ci_1',
                    'status' => 'completed',
                    'code' => "print('hi')",
                    'outputs' => [['type' => 'logs', 'logs' => "hi\n"]],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(MultiPartResult::class, $result);
        $parts = $result->getContent();
        $this->assertInstanceOf(ExecutableCodeResult::class, $parts[0]);
        $this->assertSame("print('hi')", $parts[0]->getContent());
        $this->assertSame('python', $parts[0]->getLanguage());
        $this->assertSame('ci_1', $parts[0]->getId());
        $this->assertInstanceOf(CodeExecutionResult::class, $parts[1]);
        $this->assertTrue($parts[1]->isSucceeded());
        $this->assertSame("hi\n", $parts[1]->getContent());
    }

    public function testConvertCodeInterpreterCallWithoutOutputs()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                ['type' => 'code_interpreter_call', 'id' => 'ci_2', 'status' => 'in_progress', 'code' => 'x = 1'],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ExecutableCodeResult::class, $result);
        $this->assertSame('x = 1', $result->getContent());
    }

    public function testConvertImageGenerationCall()
    {
        $converter = new ResultConverter();
        $base64 = base64_encode('image-bytes');
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                ['type' => 'image_generation_call', 'id' => 'ig_1', 'status' => 'completed', 'result' => $base64],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(BinaryResult::class, $result);
        $this->assertSame('image-bytes', $result->getContent());
        $this->assertSame('image/png', $result->getMimeType());
    }

    public function testConvertMcpCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'mcp_call',
                    'id' => 'mcp_1',
                    'status' => 'completed',
                    'server_label' => 'deepwiki',
                    'name' => 'ask_question',
                    'arguments' => '{"q":"hi"}',
                    'output' => 'the answer',
                    'error' => null,
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(McpCallResult::class, $result);
        $this->assertSame('deepwiki', $result->getServerLabel());
        $this->assertSame('ask_question', $result->getName());
        $this->assertSame('{"q":"hi"}', $result->getArguments());
        $this->assertSame('the answer', $result->getContent());
        $this->assertNull($result->getError());
    }

    public function testConvertMcpListTools()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'mcp_list_tools',
                    'id' => 'mcpl_1',
                    'server_label' => 'deepwiki',
                    'tools' => [['name' => 'ask_question', 'description' => 'Ask.']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(McpListToolsResult::class, $result);
        $this->assertSame('deepwiki', $result->getServerLabel());
        $this->assertSame([['name' => 'ask_question', 'description' => 'Ask.']], $result->getContent());
    }

    public function testConvertMcpApprovalRequest()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'mcp_approval_request',
                    'id' => 'mcpr_1',
                    'server_label' => 'deepwiki',
                    'name' => 'ask_question',
                    'arguments' => '{"q":"hi"}',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(McpApprovalRequestResult::class, $result);
        $this->assertSame('deepwiki', $result->getServerLabel());
        $this->assertSame('ask_question', $result->getName());
        $this->assertSame('{"q":"hi"}', $result->getArguments());
    }

    public function testConvertComputerCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'computer_call',
                    'id' => 'cu_1',
                    'call_id' => 'call_1',
                    'status' => 'completed',
                    'action' => ['type' => 'click', 'button' => 'left', 'x' => 1, 'y' => 2],
                    'pending_safety_checks' => [['id' => 'cu_sc_1', 'code' => 'x', 'message' => 'y']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(ComputerCallResult::class, $result);
        $this->assertSame(['type' => 'click', 'button' => 'left', 'x' => 1, 'y' => 2], $result->getContent());
        $this->assertSame('call_1', $result->getCallId());
        $this->assertSame([['id' => 'cu_sc_1', 'code' => 'x', 'message' => 'y']], $result->getPendingSafetyChecks());
    }

    public function testConvertLocalShellCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'local_shell_call',
                    'id' => 'lsh_1',
                    'call_id' => 'call_1',
                    'status' => 'completed',
                    'action' => ['type' => 'exec', 'command' => ['bash', '-lc', 'ls']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $this->assertInstanceOf(LocalShellCallResult::class, $result);
        $this->assertSame(['bash', '-lc', 'ls'], $result->getContent());
        $this->assertSame('call_1', $result->getCallId());
    }

    public function testConvertExposesIncompleteReasonAsFinishReasonMetadata()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Truncated']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertInstanceOf(FinishReason::class, $finishReason);
        $this->assertTrue($finishReason->is(FinishReasonCase::LENGTH));
        $this->assertSame('max_output_tokens', $finishReason->getRaw());
    }

    public function testConvertReportsCompletedResponseAsStop()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'message',
                    'id' => 'msg_1',
                    'role' => 'assistant',
                    'content' => [['type' => 'output_text', 'text' => 'Hello']],
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertTrue($finishReason->is(FinishReasonCase::STOP));
        $this->assertSame('completed', $finishReason->getRaw());
    }

    public function testConvertReportsCompletedToolCallResponseAsToolCall()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'status' => 'completed',
            'output' => [
                [
                    'type' => 'function_call',
                    'id' => 'fc_1',
                    'call_id' => 'call_1',
                    'name' => 'get_weather',
                    'arguments' => '{"city":"Berlin"}',
                ],
            ],
        ]);

        $result = $converter->convert(new RawHttpResult($httpResponse));

        // The Responses API reports `completed` for tool calls too; the normalized case still says TOOL_CALL.
        $finishReason = $result->getMetadata()->get('finish_reason');
        $this->assertTrue($finishReason->is(FinishReasonCase::TOOL_CALL));
        $this->assertSame('completed', $finishReason->getRaw());
    }

    public function testThrowsRuntimeExceptionWhenIncompleteResponseHasNoContent()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'status' => 'incomplete',
            'incomplete_details' => ['reason' => 'max_output_tokens'],
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Responses API response is incomplete (max_output_tokens) and contains no content.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsRuntimeExceptionWhenOutputYieldsNoContent()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'output' => [
                [
                    'type' => 'reasoning',
                    'id' => 'rs_1',
                    'summary' => [],
                ],
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain any content.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testContentFilterException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);

        $httpResponse->expects($this->exactly(1))
            ->method('toArray')
            ->willReturnCallback(static function ($throw = true) {
                if ($throw) {
                    throw new class extends \Exception implements ClientExceptionInterface {
                        public function getResponse(): ResponseInterface
                        {
                            throw new RuntimeException('Not implemented');
                        }
                    };
                }

                return [
                    'error' => [
                        'code' => 'content_filter',
                        'message' => 'Content was filtered',
                    ],
                ];
            });

        $this->expectException(ContentFilterException::class);
        $this->expectExceptionMessage('Content was filtered');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsAuthenticationExceptionOnInvalidApiKey()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(401);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Invalid API key provided',
            ],
        ]));

        $this->expectException(AuthenticationException::class);
        $this->expectExceptionMessage('Invalid API key provided');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceptionWhenNoOutput()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Response does not contain output');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceedContextSizeExceptionWhenInputExceedsContextWindow()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Your input exceeds the context window of this model. Please decrease the length of your messages.',
                'type' => 'invalid_request_error',
            ],
        ]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('Your input exceeds the context window of this model.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceedContextSizeExceptionOnContextLengthExceededCode()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Context length exceeded for this request.',
                'type' => 'invalid_request_error',
                'code' => 'context_length_exceeded',
            ],
        ]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('Context length exceeded for this request.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsExceedContextSizeExceptionOnVllmMaxModelLen()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'The engine prompt length 300072 exceeds the max_model_len 131072. Please reduce prompt.',
                'type' => 'invalid_request_error',
            ],
        ]));

        $this->expectException(ExceedContextSizeException::class);
        $this->expectExceptionMessage('exceeds the max_model_len');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponse()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);
        $httpResponse->method('getContent')->willReturn(json_encode([
            'error' => [
                'message' => 'Bad Request: invalid parameters',
            ],
        ]));

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request: invalid parameters');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsBadRequestExceptionOnBadRequestResponseWithNoResponseBody()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(400);

        $this->expectException(BadRequestException::class);
        $this->expectExceptionMessage('Bad Request');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsRateLimitExceededExceptionOn429()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(429);
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"You exceeded your current quota, please check your plan and billing details."}}');

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. You exceeded your current quota, please check your plan and billing details.');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testThrowsDetailedErrorException()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('toArray')->willReturn([
            'error' => [
                'code' => 'invalid_request_error',
                'type' => 'invalid_request',
                'param' => 'model',
                'message' => 'The model `unknown` does not exist',
            ],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "invalid_request_error"-invalid_request (model): "The model `unknown` does not exist".');

        $converter->convert(new RawHttpResult($httpResponse));
    }

    public function testStreamTransmitsUsageToResultMetadata()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => 'Hello',
            ],
            [
                'type' => 'message.delta.output_text.delta',
                'delta' => ' world',
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'usage' => [
                        'input_tokens' => 11,
                        'output_tokens' => 7,
                        'output_tokens_details' => [
                            'reasoning_tokens' => 2,
                        ],
                        'input_tokens_details' => [
                            'cached_tokens' => 3,
                        ],
                        'total_tokens' => 18,
                    ],
                    'output' => [],
                ],
            ],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertInstanceOf(TextDelta::class, $chunks[0]);
        $this->assertSame('Hello', $chunks[0]->getText());
        $this->assertInstanceOf(TextDelta::class, $chunks[1]);
        $this->assertSame(' world', $chunks[1]->getText());

        $this->assertInstanceOf(TokenUsage::class, $chunks[2]);
        $this->assertSame(11, $chunks[2]->getPromptTokens());
        $this->assertSame(7, $chunks[2]->getCompletionTokens());
        $this->assertSame(2, $chunks[2]->getThinkingTokens());
        $this->assertSame(3, $chunks[2]->getCachedTokens());
        $this->assertSame(18, $chunks[2]->getTotalTokens());
    }

    public function testStreamWithToolCalls()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [
                        [
                            'type' => 'function_call',
                            'id' => 'call_456',
                            'name' => 'get_weather',
                            'arguments' => '{"city": "Berlin"}',
                        ],
                    ],
                ],
            ],
        ];

        $raw = new class($httpResponse, $events) implements RawResultInterface {
            /**
             * @param array<array<string, mixed>> $events
             */
            public function __construct(
                private readonly ResponseInterface $response,
                private readonly array $events,
            ) {
            }

            public function getData(): array
            {
                return [];
            }

            public function getDataStream(): iterable
            {
                foreach ($this->events as $event) {
                    yield $event;
                }
            }

            public function getObject(): object
            {
                return $this->response;
            }
        };

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = [];
        foreach ($streamResult->getContent() as $part) {
            $chunks[] = $part;
        }

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_456', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamWithToolCallOutputItemDone()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.output_item.done',
                'item' => [
                    'type' => 'function_call',
                    'id' => 'call_456',
                    'name' => 'get_weather',
                    'arguments' => '{"city": "Berlin"}',
                ],
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_456', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamWithToolCallOutputItemDoneUsesCallIdWhenIdIsMissing()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.output_item.done',
                'item' => [
                    'type' => 'function_call',
                    'id' => null,
                    'call_id' => 'call_789',
                    'name' => 'get_weather',
                    'arguments' => '{"city": "Berlin"}',
                ],
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);
        $streamResult = $converter->convert($raw, ['stream' => true]);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(2, $chunks);
        $this->assertInstanceOf(ToolCallComplete::class, $chunks[0]);
        $this->assertInstanceOf(MetadataDelta::class, $chunks[1]);
        $this->assertTrue($chunks[1]->getValue()->is(FinishReasonCase::TOOL_CALL));
        $toolCalls = $chunks[0]->getToolCalls();
        $this->assertCount(1, $toolCalls);
        $this->assertSame('call_789', $toolCalls[0]->getId());
        $this->assertSame('get_weather', $toolCalls[0]->getName());
        $this->assertSame(['city' => 'Berlin'], $toolCalls[0]->getArguments());
    }

    public function testStreamThrowsWhenResponseCompletedIsMissing()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $raw = new InMemoryRawResult([], [
            [
                'type' => 'response.output_text.delta',
                'delta' => 'Hello',
            ],
        ], $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->expectException(IncompleteStreamException::class);
        $this->expectExceptionMessage('Responses API stream ended before response.completed.');

        iterator_to_array($streamResult->getContent());
    }

    /**
     * @param list<array<string, mixed>> $events
     */
    #[DataProvider('provideStreamTerminalErrorEvents')]
    public function testStreamThrowsExceptionOnTerminalErrorEvents(array $events, string $expectedMessage)
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $streamResult = $converter->convert(new InMemoryRawResult([], $events, $httpResponse), ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($expectedMessage);

        iterator_to_array($streamResult->getContent());
    }

    /**
     * @return iterable<string, array{0: list<array<string, mixed>>, 1: string}>
     */
    public static function provideStreamTerminalErrorEvents(): iterable
    {
        yield 'top-level error' => [[
            [
                'type' => 'error',
                'code' => 'insufficient_quota',
                'message' => 'You exceeded your current quota',
                'param' => null,
                'sequence_number' => 2,
            ],
        ], 'Error "insufficient_quota"-- (-): "You exceeded your current quota".'];

        yield 'response failed' => [[
            [
                'type' => 'response.failed',
                'response' => [
                    'error' => [
                        'code' => 'server_error',
                        'message' => 'The model failed to generate a response',
                    ],
                ],
            ],
        ], 'Error "server_error"-- (-): "The model failed to generate a response".'];

        yield 'response incomplete' => [[
            [
                'type' => 'response.incomplete',
                'response' => [
                    'incomplete_details' => [
                        'reason' => 'max_tokens',
                    ],
                ],
            ],
        ], 'Responses API response is incomplete (max_tokens).'];
    }

    public function testStreamThrowsWhenTruncatedAtMaxOutputTokens()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'type' => 'response.incomplete',
            'response' => ['incomplete_details' => ['reason' => 'max_output_tokens']],
        ]], $httpResponse), ['stream' => true]);

        $this->expectException(MaxOutputTokensException::class);
        $this->expectExceptionMessage('OpenResponses truncated the response after reaching the output token limit.');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamThrowsRateLimitExceptionOnRateLimitEvent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'type' => 'error',
            'code' => 'rate_limit_exceeded',
            'message' => 'Rate limit reached for requests',
        ]], $httpResponse), ['stream' => true]);

        $this->expectException(RateLimitExceededException::class);
        $this->expectExceptionMessage('Rate limit exceeded. Error "rate_limit_exceeded"-- (-): "Rate limit reached for requests".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamThrowsServerExceptionOnServerErrorEvent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $streamResult = $converter->convert(new InMemoryRawResult([], [[
            'type' => 'response.failed',
            'response' => [
                'error' => [
                    'code' => 'server_error',
                    'message' => 'The model failed to generate a response',
                ],
            ],
        ]], $httpResponse), ['stream' => true]);

        $this->expectException(ServerException::class);
        $this->expectExceptionMessage('Server error. Error "server_error"-- (-): "The model failed to generate a response".');

        iterator_to_array($streamResult->getContent());
    }

    public function testStreamThrowsExceptionOnErrorEvent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'error',
                'error' => [
                    'type' => 'insufficient_quota',
                    'code' => 'insufficient_quota',
                    'message' => 'You exceeded your current quota',
                    'param' => null,
                ],
                'sequence_number' => 2,
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Error "insufficient_quota"-insufficient_quota (-): "You exceeded your current quota".');

        foreach ($streamResult->getContent() as $part) {
            // Iterate to trigger the generator
        }
    }

    public function testStreamWithReasoningContent()
    {
        $converter = new ResultConverter();

        $httpResponse = $this->createStub(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(200);

        $events = [
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => 'Let me think',
            ],
            [
                'type' => 'response.reasoning_summary_text.delta',
                'delta' => ' about this...',
            ],
            [
                'type' => 'response.reasoning_summary_text.done',
            ],
            [
                'type' => 'response.output_text.delta',
                'delta' => 'The answer is 42.',
            ],
            [
                'type' => 'response.completed',
                'response' => [
                    'output' => [],
                ],
            ],
        ];

        $raw = new InMemoryRawResult([], $events, $httpResponse);

        $streamResult = $converter->convert($raw, ['stream' => true]);

        $this->assertInstanceOf(StreamResult::class, $streamResult);

        $chunks = iterator_to_array($streamResult->getContent());

        $this->assertCount(6, $chunks);
        $this->assertInstanceOf(ThinkingStart::class, $chunks[0]);
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[1]);
        $this->assertSame('Let me think', $chunks[1]->getThinking());
        $this->assertInstanceOf(ThinkingDelta::class, $chunks[2]);
        $this->assertSame(' about this...', $chunks[2]->getThinking());
        $this->assertInstanceOf(ThinkingComplete::class, $chunks[3]);
        $this->assertSame('Let me think about this...', $chunks[3]->getThinking());
        $this->assertInstanceOf(TextDelta::class, $chunks[4]);
        $this->assertSame('The answer is 42.', $chunks[4]->getText());
    }

    public function testThrowsServerExceptionOnServerErrorStatusBeforeStreaming()
    {
        $converter = new ResultConverter();
        $httpResponse = $this->createMock(ResponseInterface::class);
        $httpResponse->method('getStatusCode')->willReturn(500);
        $httpResponse->method('getContent')->willReturn('{"error":{"message":"Service Unavailable"}}');

        try {
            $converter->convert(new RawHttpResult($httpResponse), ['stream' => true]);
            $this->fail('Expected a ServerException to be thrown.');
        } catch (ServerException $e) {
            $this->assertSame(500, $e->getStatusCode());
            $this->assertStringContainsString('Service Unavailable', $e->getMessage());
        }
    }
}
