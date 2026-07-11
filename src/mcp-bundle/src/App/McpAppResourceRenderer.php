<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\App;

use Mcp\Schema\Content\TextResourceContents;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use Symfony\AI\McpBundle\Exception\LogicException;

/**
 * Shared resource handler for all template-based MCP Apps.
 *
 * The MCP SDK resolves a manual resource handler's instance from the container by the handler's class
 * name, so every template app shares this one service (keyed by its FQCN) rather than a per-app service.
 * The SDK fills the `$uri` argument with the requested resource URI; this handler looks up the matching
 * template and content metadata and renders it.
 *
 * @phpstan-type AppConfig array{template: string, contentMeta: ?UiResourceContentMeta}
 */
final class McpAppResourceRenderer
{
    /**
     * @param array<string, AppConfig> $apps URI => template + content metadata, populated by the compiler pass
     */
    public function __construct(
        private readonly McpAppRenderer $renderer,
        private readonly array $apps,
    ) {
    }

    public function __invoke(string $uri): TextResourceContents
    {
        if (!isset($this->apps[$uri])) {
            throw new LogicException(\sprintf('No MCP App is registered for resource URI "%s".', $uri));
        }

        return $this->renderer->render($uri, $this->apps[$uri]['template'], [], $this->apps[$uri]['contentMeta']);
    }
}
