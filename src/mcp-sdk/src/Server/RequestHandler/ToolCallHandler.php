<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Server\RequestHandler;

use Symfony\AI\McpSdk\Capability\Tool\ToolCall;
use Symfony\AI\McpSdk\Capability\Tool\ToolCallResult;
use Symfony\AI\McpSdk\Capability\Tool\ToolExecutorInterface;
use Symfony\AI\McpSdk\Exception\ExceptionInterface;
use Symfony\AI\McpSdk\Exception\InvalidArgumentException;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;
use Symfony\AI\McpSdk\Message\StreamableResponse;

final class ToolCallHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly ToolExecutorInterface $toolExecutor,
    ) {
    }

    public function createResponse(Request $message): StreamableResponse|Response|Error
    {
        $name = $message->params['name'];
        $arguments = $message->params['arguments'] ?? [];

        try {
            $result = $this->toolExecutor->call(new ToolCall(uniqid('', true), $name, $arguments));
        } catch (ExceptionInterface $e) {
            return Error::internalError($message->id, 'Error while executing tool');
        }

        if ($result instanceof \Traversable) {
            return new StreamableResponse(
                function () use ($message, $result): \Generator {
                    foreach ($result as $resultDetail) {
                        if ($resultDetail instanceof Notification) {
                            yield [
                                'jsonrpc' => '2.0',
                                'method' => 'notifications/progress',
                                'params' => [
                                    'progress' => 10,
                                    'progressToken' => $message->params['_meta']['progressToken'],
                                ]
                                /*'method' => 'notifications/message',
                                'params' => [
                                    "level" => 'info',
                                    'data' => 'In progress'
                                ]*/
                            ];
                        } elseif ($resultDetail instanceof ToolCallResult) {
                            yield $this->getResponse($message, $resultDetail);
                            break;
                        } else {
                            throw new InvalidArgumentException('Unsupported tool result type: '.\get_class($resultDetail));
                        }
                    }
                }
            );
        }

        return $this->getResponse($message, $result);
    }

    protected function getResponse(Request $message, ToolCallResult $result): Response
    {
        $content = match ($result->type) {
            'text' => [
                'type' => 'text',
                'text' => $result->result,
            ],
            'image', 'audio' => [
                'type' => $result->type,
                'data' => $result->result,
                'mimeType' => $result->mimeType,
            ],
            'resource' => [
                'type' => 'resource',
                'resource' => [
                    'uri' => $result->uri,
                    'mimeType' => $result->mimeType,
                    'text' => $result->result,
                ],
            ],
            // TODO better exception
            default => throw new InvalidArgumentException('Unsupported tool result type: '.$result->type),
        };

        return new Response($message->id, [
            'content' => [$content], // TODO: allow multiple `ToolCallResult`s in the future
            'isError' => $result->isError,
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'tools/call';
    }
}
