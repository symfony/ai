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

use Symfony\AI\McpSdk\Capability\Resource\ResourceRead;
use Symfony\AI\McpSdk\Capability\Resource\ResourceReaderInterface;
use Symfony\AI\McpSdk\Exception\ExceptionInterface;
use Symfony\AI\McpSdk\Message\Error;
use Symfony\AI\McpSdk\Message\Request;
use Symfony\AI\McpSdk\Message\Response;

final class ResourceReadHandler extends BaseRequestHandler
{
    public function __construct(
        private readonly ResourceReaderInterface $reader,
    ) {
    }

    public function createResponse(Request $message): Response|Error
    {
        $uri = $message->params['uri'];

        try {
            $result = $this->reader->read(new ResourceRead(uniqid('', true), $uri));
        } catch (ExceptionInterface) {
            return Error::internalError($message->id, 'Error while reading resource');
        }

        return new Response($message->id, [
            'contents' => [
                [
                    'uri' => $result->uri,
                    'mimeType' => $result->mimeType,
                    $result->type => $result->result,
                ],
            ],
        ]);
    }

    protected function supportedMethod(): string
    {
        return 'resources/read';
    }
}
