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

namespace Symfony\AI\McpSdk\Server\RequestHandler;

use Symfony\AI\McpSdk\Capability\Tool\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Tool\MetadataInterface;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class ToolListHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly CollectionInterface $toolCollection,
    ) {
    }

    public function createResponse(Request $message): Response
    {
        return new Response($message->id, [
            'tools' => array_map(function (MetadataInterface $tool) {
                $inputSchema = $tool->getInputSchema();

                return [
                    'name' => $tool->getName(),
                    'description' => $tool->getDescription(),
                    'inputSchema' => [] === $inputSchema ? [
                        'type' => 'object',
                        '$schema' => 'http://json-schema.org/draft-07/schema#',
                    ] : $inputSchema,
                ];
            }, $this->toolCollection->getMetadata()),
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'tools/list';
    }
}
