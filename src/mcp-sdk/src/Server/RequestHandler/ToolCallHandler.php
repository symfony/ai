<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace PhpLlm\McpSdk\Server\RequestHandler;

use PhpLlm\McpSdk\Capability\Tool\ToolCall;
use PhpLlm\McpSdk\Capability\Tool\ToolExecutorInterface;
use PhpLlm\McpSdk\Exception\ExceptionInterface;
use PhpLlm\McpSdk\Message\Error;
use PhpLlm\McpSdk\Message\Request;
use PhpLlm\McpSdk\Message\Response;

final class ToolCallHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly ToolExecutorInterface $toolExecutor,
    ) {
    }

    public function createResponse(Request $message): Response|Error
    {
        $name = $message->params['name'];
        $arguments = $message->params['arguments'] ?? [];

        try {
            $result = $this->toolExecutor->call(new ToolCall(uniqid('', true), $name, $arguments));
        } catch (ExceptionInterface) {
            return Error::internalError($message->id, 'Error while executing tool');
        }

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
            default => throw new \InvalidArgumentException('Unsupported tool result type: '.$result->type),
        };

        return new Response($message->id, [
            'content' => $content,
            'isError' => $result->isError,
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'tools/call';
    }
}
