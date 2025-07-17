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

use Symfony\AI\McpSdk\Capability\Prompt\CollectionInterface;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class PromptListHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly CollectionInterface $collection,
        private readonly int $pageSize = 20,
    ) {
    }

    public function createResponse(Request $message): Response
    {
        $nextCursor = null;
        $prompts = [];

        $metadataList = $this->collection->getMetadata(
            $this->pageSize,
            $message->params['cursor'] ?? null
        );

        foreach ($metadataList as $metadata) {
            $nextCursor = $metadata->getName();
            $result = [
                'name' => $metadata->getName(),
            ];

            $description = $metadata->getDescription();
            if (null !== $description) {
                $result['description'] = $description;
            }

            $arguments = [];
            foreach ($metadata->getArguments() as $data) {
                $argument = [
                    'name' => $data['name'],
                    'required' => $data['required'] ?? false,
                ];

                if (isset($data['description'])) {
                    $argument['description'] = $data['description'];
                }
                $arguments[] = $argument;
            }

            if ([] !== $arguments) {
                $result['arguments'] = $arguments;
            }

            $prompts[] = $result;
        }

        $result = [
            'prompts' => $prompts,
        ];

        if (null !== $nextCursor && \count($prompts) === $this->pageSize) {
            $result['nextCursor'] = $nextCursor;
        }

        return new Response($message->id, $result);
    }

    protected function supportedMethod(): string
    {
        return 'prompts/list';
    }
}
