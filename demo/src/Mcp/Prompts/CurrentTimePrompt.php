<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Mcp\Prompts;

use Mcp\Capability\Attribute\McpPrompt;

class CurrentTimePrompt
{
    /**
     * @return array{role: 'user', content: string}[]
     */
    #[McpPrompt(name: 'time-analysis')]
    public function getTimeAnalysisPrompt(): array
    {
        return [
            [
                'role' => 'user',
                'content' => 'You are a time management expert. Analyze what time of day it is and suggest appropriate activities for this time.',
            ],
        ];
    }
}
