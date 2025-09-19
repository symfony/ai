<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Routing;

use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteLoader extends Loader
{
    private bool $loaded = false;

    public function __construct(
        private bool $sseTransportEnabled,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new LogicException('Do not add the "mcp" loader twice.');
        }

        $this->loaded = true;

        if (!$this->sseTransportEnabled) {
            return new RouteCollection();
        }

        $collection = new RouteCollection();

        $collection->add('_mcp_sse', new Route('/_mcp/sse', ['_controller' => 'mcp.server.controller::sse'], methods: ['GET']));
        $collection->add('_mcp_messages', new Route('/_mcp/messages/{id}', ['_controller' => 'mcp.server.controller::messages'], methods: ['POST']));

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'mcp' === $type;
    }
}
