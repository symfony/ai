<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests\Fixtures\Tool;

class ToolWithBackedEnums
{
    /**
     * Search using enum parameters without attributes.
     *
     * @param array<string> $searchTerms The search terms
     * @param EnumMode      $mode        The search mode
     * @param EnumPriority  $priority    The search priority
     * @param EnumMode|null $fallback    Optional fallback mode
     *
     * @return array<string, mixed>
     */
    public function __invoke(array $searchTerms, EnumMode $mode, EnumPriority $priority, ?EnumMode $fallback = null): array
    {
        return [
            'terms' => $searchTerms,
            'mode' => $mode->value,
            'priority' => $priority->value,
            'fallback' => $fallback?->value,
        ];
    }
}
