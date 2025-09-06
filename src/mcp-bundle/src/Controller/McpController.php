<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Controller;

use Mcp\Server;
use Mcp\Server\Transport\Sse\Store\CachePoolStore;
use Mcp\Server\Transport\Sse\StreamTransport;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Uid\Uuid;

final readonly class McpController
{
    public function __construct(
        private Server $server,
        private CachePoolStore $store,
        private UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function sse(): StreamedResponse
    {
        $id = Uuid::v4();
        $endpoint = $this->urlGenerator->generate('_mcp_messages', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        $transport = new StreamTransport($endpoint, $this->store, $id);

        return new StreamedResponse(fn () => $this->server->connect($transport), headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    public function messages(Request $request, Uuid $id): Response
    {
        $this->store->push($id, $request->getContent());

        return new Response();
    }
}
