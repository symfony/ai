<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message\TemplateRenderer;

use Symfony\AI\Platform\Exception\InvalidArgumentException;

/**
 * Registry for managing template renderers.
 *
 * Provides access to template renderers based on template type.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class TemplateRendererRegistry implements TemplateRendererRegistryInterface
{
    /**
     * @var TemplateRendererInterface[]
     */
    private readonly array $renderers;

    /**
     * @param iterable<TemplateRendererInterface> $renderers
     */
    public function __construct(iterable $renderers)
    {
        $this->renderers = $renderers instanceof \Traversable ? iterator_to_array($renderers) : $renderers;
    }

    public function getRenderer(string $type): TemplateRendererInterface
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($type)) {
                return $renderer;
            }
        }

        throw new InvalidArgumentException(\sprintf('No renderer found for template type "%s".', $type));
    }
}
