<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Attribute;

/**
 * Registers a class as an MCP App: a UI resource (served as `text/html;profile=mcp-app`) that a host
 * renders in an iframe, together with the tool that feeds it.
 *
 * The class is the single hub for the app (à la Symfony UX LiveComponent): the attribute carries the
 * tool's identity (`$name`/`$description`) and the static HTML shell (`$template`), the constructor
 * carries service dependencies, and a handler method (`$method`, default `render`) produces the tool
 * result. The bundle registers the UI resource (including the `_meta.ui` resource-descriptor marker
 * that a plain attribute cannot express), registers the tool with its `ui` link auto-set to this app
 * (`resourceUri` + visibility `[model, app]`), and enables the MCP Apps server extension.
 *
 * The HTML body comes from `$template` (the common case; requires Twig) or, for a dynamic shell, from
 * an `__invoke(): TextResourceContents` method on the class.
 *
 * The tool is registered only when the handler `$method` exists on the class; an app without it is a
 * static screen with no tool. The tool's input schema is derived from the method signature.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class AsMcpApp
{
    /**
     * @param string|null   $uri            the `ui://` resource URI; defaults to `ui://<kebab-short-class-name>`
     * @param string|null   $name           the linked tool's name (model-facing); defaults to the URI slug with dashes replaced by underscores. The resource name is derived from the URI.
     * @param string|null   $title          optional human-readable title shown by hosts (tool + resource)
     * @param string|null   $description    the linked tool's description (model-facing)
     * @param string|null   $template       Twig template name for the HTML shell (e.g. `@App/mcp/dashboard.html.twig`)
     * @param string|null   $method         handler method producing the tool result; defaults to `render`. Set explicitly to require a differently-named method.
     * @param string|null   $toolTemplate   Twig fragment template the bundle renders for the primary tool. When set, the handler
     *                                      method returns a context array and the bundle injects the rendered HTML as the `html`
     *                                      field of the tool result (the HTML-over-the-wire path) — no Twig in the handler.
     *                                      See {@see AsMcpAppTool} for additional tool methods.
     * @param bool|null     $prefersBorder  content-meta: hint that the host should render a border around the iframe
     * @param string|null   $domain         content-meta: associated domain for the app
     * @param string[]|null $cspConnect     content-meta CSP: domains allowed for network requests (fetch/XHR/WebSocket)
     * @param string[]|null $cspResource    content-meta CSP: domains allowed for static resources (img/script/style)
     * @param string[]|null $cspFrame       content-meta CSP: domains allowed for nested iframes
     * @param string[]|null $cspBaseUri     content-meta CSP: domains allowed as base URI origins
     * @param bool          $camera         content-meta permission: request camera access
     * @param bool          $microphone     content-meta permission: request microphone access
     * @param bool          $geolocation    content-meta permission: request geolocation access
     * @param bool          $clipboardWrite content-meta permission: request clipboard-write access
     */
    public function __construct(
        public ?string $uri = null,
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $template = null,
        public ?string $method = null,
        public ?string $toolTemplate = null,
        public ?bool $prefersBorder = null,
        public ?string $domain = null,
        public ?array $cspConnect = null,
        public ?array $cspResource = null,
        public ?array $cspFrame = null,
        public ?array $cspBaseUri = null,
        public bool $camera = false,
        public bool $microphone = false,
        public bool $geolocation = false,
        public bool $clipboardWrite = false,
    ) {
    }
}
