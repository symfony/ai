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

namespace App;

use PhpLlm\McpSdk\Capability\PromptChain;
use PhpLlm\McpSdk\Capability\ResourceChain;
use PhpLlm\McpSdk\Capability\ToolChain;
use PhpLlm\McpSdk\Server\NotificationHandler\InitializedHandler;
use PhpLlm\McpSdk\Server\NotificationHandlerInterface;
use PhpLlm\McpSdk\Server\RequestHandler\InitializeHandler;
use PhpLlm\McpSdk\Server\RequestHandler\PingHandler;
use PhpLlm\McpSdk\Server\RequestHandler\PromptGetHandler;
use PhpLlm\McpSdk\Server\RequestHandler\PromptListHandler;
use PhpLlm\McpSdk\Server\RequestHandler\ResourceListHandler;
use PhpLlm\McpSdk\Server\RequestHandler\ResourceReadHandler;
use PhpLlm\McpSdk\Server\RequestHandler\ToolCallHandler;
use PhpLlm\McpSdk\Server\RequestHandler\ToolListHandler;
use PhpLlm\McpSdk\Server\RequestHandlerInterface;

class Builder
{
    /**
     * @return list<RequestHandlerInterface>
     */
    public static function buildRequestHandlers(): array
    {
        $promptManager = new PromptChain([
            new ExamplePrompt(),
        ]);

        $resourceManager = new ResourceChain([
            new ExampleResource(),
        ]);

        $toolManager = new ToolChain([
            new ExampleTool(),
        ]);

        return [
            new InitializeHandler(),
            new PingHandler(),
            new PromptListHandler($promptManager),
            new PromptGetHandler($promptManager),
            new ResourceListHandler($resourceManager),
            new ResourceReadHandler($resourceManager),
            new ToolCallHandler($toolManager),
            new ToolListHandler($toolManager),
        ];
    }

    /**
     * @return list<NotificationHandlerInterface>
     */
    public static function buildNotificationHandlers(): array
    {
        return [
            new InitializedHandler(),
        ];
    }
}
