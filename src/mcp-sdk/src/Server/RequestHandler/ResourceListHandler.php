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

use Symfony\AI\McpSdk\Capability\Resource\CollectionInterface;
use Symfony\AI\McpSdk\Capability\Resource\MetadataInterface;
use Symfony\AI\McpSdk\Message\Notification;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class ResourceListHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly CollectionInterface $collection,
    ) {
    }

    public function createResponse(Request|Notification $message): Response
    {
        return new Response($message->id, [
            'resources' => array_map(function (MetadataInterface $metadata) {
                $result = [
                    'uri' => $metadata->getUri(),
                    'name' => $metadata->getName(),
                ];

                $description = $metadata->getDescription();
                if (null !== $description) {
                    $result['description'] = $description;
                }

                $mimeType = $metadata->getMimeType();
                if (null !== $mimeType) {
                    $result['mimeType'] = $mimeType;
                }

                $size = $metadata->getSize();
                if (null !== $size) {
                    $result['size'] = $size;
                }

                return $result;
            }, $this->collection->getMetadata()),
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'resources/list';
    }
}
