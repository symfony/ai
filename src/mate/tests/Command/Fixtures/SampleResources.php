<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Tests\Command\Fixtures;

use Mcp\Capability\Attribute\McpResource;
use Mcp\Capability\Attribute\McpResourceTemplate;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SampleResources
{
    #[McpResource(
        uri: 'sample://greeting',
        name: 'sample-greeting',
        description: 'A static greeting resource for tests',
        mimeType: 'text/plain',
    )]
    public function getGreeting(): string
    {
        return 'Hello from the Mate test fixture!';
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    #[McpResourceTemplate(
        uriTemplate: 'sample://echo/{message}',
        name: 'sample-echo',
        description: 'Echoes the message back as a resource',
        mimeType: 'text/plain',
    )]
    public function getEcho(string $message): array
    {
        return [
            'uri' => "sample://echo/{$message}",
            'mimeType' => 'text/plain',
            'text' => "echo: {$message}",
        ];
    }
}
