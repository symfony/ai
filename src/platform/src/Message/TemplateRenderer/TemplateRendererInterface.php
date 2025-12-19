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

use Symfony\AI\Platform\Message\Template;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface TemplateRendererInterface
{
    public function supports(string $type): bool;

    /**
     * @param array<string, mixed> $variables
     */
    public function render(Template $template, array $variables): string;
}
