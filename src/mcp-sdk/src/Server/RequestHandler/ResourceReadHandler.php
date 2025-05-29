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

use PhpLlm\McpSdk\Capability\Resource\ResourceRead;
use PhpLlm\McpSdk\Capability\Resource\ResourceReaderInterface;
use PhpLlm\McpSdk\Exception\ExceptionInterface;
use PhpLlm\McpSdk\Message\Error;
use PhpLlm\McpSdk\Message\Request;
use PhpLlm\McpSdk\Message\Response;

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
