<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Fixtures;

use Mcp\Capability\Attribute\McpPrompt;

#[McpPrompt(name: 'test_prompt', description: 'A test prompt')]
class TestPromptService
{
    public function __invoke(string $topic): string
    {
        return "Tell me about $topic";
    }
}
