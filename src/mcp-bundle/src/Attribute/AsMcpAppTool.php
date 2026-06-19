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
 * Declares an additional tool on an {@see AsMcpApp} class, beyond the app's primary tool.
 *
 * The annotated method is registered as a tool linked to the enclosing app (its `ui` link points at the
 * app's `ui://` resource), and its input schema is derived from the method signature. When `$template`
 * is set, the method returns a context array and the bundle renders that template into the `html` field
 * of the tool result (the HTML-over-the-wire path) — so the developer writes no Twig in the handler.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
#[\Attribute(\Attribute::TARGET_METHOD)]
final class AsMcpAppTool
{
    /**
     * @param string|null $name        the tool name (model-facing); defaults to the method name converted to snake_case
     * @param string|null $title       optional human-readable title shown by hosts
     * @param string|null $description the tool description (model-facing)
     * @param string|null $template    Twig fragment template the bundle renders into the result's `html` field; when set,
     *                                 the method must return a context array
     * @param bool        $appOnly     when true the tool is visible to the app only (`[app]`); otherwise to the model and
     *                                 the app (`[model, app]`)
     */
    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $description = null,
        public ?string $template = null,
        public bool $appOnly = false,
    ) {
    }
}
