<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\PromptTemplate;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface PromptTemplateInterface extends \Stringable
{
    /**
     * Renders the template with the provided values.
     *
     * @param array<string, mixed> $values
     */
    public function format(array $values = []): string;

    /**
     * Returns the original template string.
     */
    public function getTemplate(): string;
}
