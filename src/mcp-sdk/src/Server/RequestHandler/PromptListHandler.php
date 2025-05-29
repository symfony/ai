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

use Symfony\AI\McpSdk\Capability\Prompt\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Prompt\MetadataInterface;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class PromptListHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly CollectionInterface $collection,
    ) {
    }

    public function createResponse(Request|Notification $message): Response
    {
        return new Response($message->id, [
            'prompts' => array_map(function (MetadataInterface $metadata) {
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

                return $result;
            }, $this->collection->getMetadata()),
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'prompts/list';
    }
}
