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
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Exception\LogicException;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

final class RouteLoader extends Loader
{
    private bool $loaded = false;

    /**
     * @param list<array{name: string, path: string, controller: string}> $servers the MCP servers exposed over HTTP
     */
    public function __construct(
        private array $servers,
    ) {
        parent::__construct();
    }

    public function load(mixed $resource, ?string $type = null): RouteCollection
    {
        if ($this->loaded) {
            throw new LogicException('Do not add the "mcp" loader twice.');
        }

        $this->loaded = true;

        $collection = new RouteCollection();

        foreach ($this->servers as $server) {
            $collection->add('_mcp_endpoint_'.$server['name'], new Route(
                $server['path'],
                ['_controller' => $server['controller']],
                methods: [Request::METHOD_GET, Request::METHOD_POST, Request::METHOD_DELETE, Request::METHOD_OPTIONS],
            ));
        }

        return $collection;
    }

    public function supports(mixed $resource, ?string $type = null): bool
    {
        return 'mcp' === $type;
    }
}
