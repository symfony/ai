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
interface TemplateRendererRegistryInterface
{
    /**
     * @throws InvalidArgumentException If no renderer supports the type
     */
    public function getRenderer(string $type): TemplateRendererInterface;
}
