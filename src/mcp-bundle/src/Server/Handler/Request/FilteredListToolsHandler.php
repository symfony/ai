<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Server\Handler\Request;

use Mcp\Capability\RegistryInterface;
use Mcp\Exception\InvalidCursorException;
use Mcp\Schema\JsonRpc\Request;
use Mcp\Schema\JsonRpc\Response;
use Mcp\Schema\Request\ListToolsRequest;
use Mcp\Schema\Result\ListToolsResult;
use Mcp\Schema\Tool;
use Mcp\Server\Handler\Request\RequestHandlerInterface;
use Mcp\Server\Session\SessionInterface;
use Symfony\AI\McpBundle\Server\ToolListFilterInterface;

/**
 * @implements RequestHandlerInterface<ListToolsResult>
 *
 * @author Ousama Ben Younes <2910651+ousamabenyounes@users.noreply.github.com>
 */
final class FilteredListToolsHandler implements RequestHandlerInterface
{
    private const DEFAULT_PAGE_SIZE = 20;

    public function __construct(
        private readonly RegistryInterface $registry,
        private readonly ToolListFilterInterface $filter,
        private readonly int $pageSize = self::DEFAULT_PAGE_SIZE,
    ) {
    }

    public function supports(Request $request): bool
    {
        return $request instanceof ListToolsRequest;
    }

    /**
     * @return Response<ListToolsResult>
     *
     * @throws InvalidCursorException When the cursor is invalid
     */
    public function handle(Request $request, SessionInterface $session): Response
    {
        \assert($request instanceof ListToolsRequest);

        $tools = array_values(array_filter(
            $this->registry->getTools()->references,
            fn (Tool $tool): bool => $this->filter->isVisible($tool),
        ));
        [$tools, $nextCursor] = $this->paginate($tools, $request->cursor);

        return new Response(
            $request->getId(),
            new ListToolsResult($tools, $nextCursor),
        );
    }

    /**
     * @param list<Tool> $tools
     *
     * @return array{list<Tool>, ?string}
     */
    private function paginate(array $tools, ?string $cursor): array
    {
        $offset = 0;
        if (null !== $cursor) {
            $decodedCursor = base64_decode($cursor, true);
            if (false === $decodedCursor || !is_numeric($decodedCursor)) {
                throw new InvalidCursorException($cursor);
            }

            $offset = (int) $decodedCursor;
            if ($offset < 0 || $offset > \count($tools)) {
                throw new InvalidCursorException($cursor);
            }
        }

        $nextOffset = $offset + $this->pageSize;
        $nextCursor = $nextOffset < \count($tools) ? base64_encode((string) $nextOffset) : null;

        return [\array_slice($tools, $offset, $this->pageSize), $nextCursor];
    }
}
