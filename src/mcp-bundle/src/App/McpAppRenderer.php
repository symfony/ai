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
use Mcp\Schema\Extension\Apps\McpApps;
use Mcp\Schema\Extension\Apps\UiResourceContentMeta;
use Twig\Environment;

/**
 * Renders a Twig template into the {@see TextResourceContents} body of an MCP App UI resource,
 * with the MCP Apps mime type and (optional) content-level `_meta.ui` metadata.
 *
 * Registered only when Twig is available. Used by {@see McpAppResourceRenderer} to render template-based
 * {@see \Symfony\AI\McpBundle\Attribute\AsMcpApp} apps; can also be injected into a custom handler to
 * render with dynamic context.
 */
final class McpAppRenderer
{
    public const SERVICE_ID = 'mcp.app.renderer';

    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function render(
        string $uri,
        string $template,
        array $context = [],
        ?UiResourceContentMeta $contentMeta = null,
    ): TextResourceContents {
        return new TextResourceContents(
            uri: $uri,
            mimeType: McpApps::MIME_TYPE,
            text: $this->twig->render($template, $context),
            meta: null !== $contentMeta ? ['ui' => $contentMeta] : null,
        );
    }

    /**
     * Renders a Twig template to a raw HTML string, for use as the `html` field of a tool result
     * (the HTML-over-the-wire path: the base template's default `render(model)` injects `model.html`).
     *
     * @param array<string, mixed> $context
     */
    public function renderFragment(string $template, array $context = []): string
    {
        return $this->twig->render($template, $context);
    }
}
