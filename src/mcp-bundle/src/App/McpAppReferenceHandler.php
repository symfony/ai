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

use Mcp\Capability\Registry\ElementReference;
use Mcp\Capability\Registry\ReferenceHandlerInterface;
use Symfony\AI\McpBundle\Exception\LogicException;

/**
 * Decorates the SDK reference handler so MCP App tool methods can stay free of Twig.
 *
 * The wrapped (inner) handler invokes the real tool method — keeping the SDK's reflection-derived input
 * schema and argument mapping intact. This decorator then, for a tool whose handler is registered with a
 * fragment template (the primary tool's `AsMcpApp::$toolTemplate` or an {@see \Symfony\AI\McpBundle\Attribute\AsMcpAppTool}
 * method's `template`), renders that template with the method's returned context array and injects the
 * result as the `html` field (the HTML-over-the-wire path). Every other element — resources, prompts, and
 * tools without a template — passes through unchanged.
 *
 * Registered only when at least one app tool declares a template; see {@see \Symfony\AI\McpBundle\DependencyInjection\McpPass}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
final class McpAppReferenceHandler implements ReferenceHandlerInterface
{
    /**
     * @param array<string, string> $templates map of "<serviceId>::<method>" => fragment template name
     */
    public function __construct(
        private readonly ReferenceHandlerInterface $inner,
        private readonly McpAppRenderer $renderer,
        private readonly array $templates,
    ) {
    }

    public function handle(ElementReference $reference, array $arguments): mixed
    {
        $result = $this->inner->handle($reference, $arguments);

        $handler = $reference->handler;
        if (!\is_array($handler) || !\is_string($handler[0])) {
            return $result;
        }

        $key = $handler[0].'::'.$handler[1];
        $template = $this->templates[$key] ?? null;
        if (null === $template) {
            return $result;
        }

        if (!\is_array($result) || (array_is_list($result) && [] !== $result)) {
            throw new LogicException(\sprintf('The MCP App tool "%s" declares the template "%s" but returned "%s"; a template-bound tool method must return an array context.', $key, $template, get_debug_type($result)));
        }

        return ['html' => $this->renderer->renderFragment($template, $result)] + $result;
    }
}
