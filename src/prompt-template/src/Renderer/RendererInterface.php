<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate\Renderer;

use Symfony\AI\PromptTemplate\Exception\RenderingException;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface RendererInterface
{
    /**
     * @param array<string, mixed> $values
     *
     * @throws RenderingException when rendering fails
     */
    public function render(string $template, array $values): string;
}
