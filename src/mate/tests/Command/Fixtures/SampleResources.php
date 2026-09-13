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

use Symfony\AI\Mate\Attribute\MateResource;
use Symfony\AI\Mate\Attribute\MateResourceTemplate;
use Symfony\AI\Mate\Encoding\ResponseEncoder;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SampleResources
{
    #[MateResource(
        uri: 'sample://greeting',
        name: 'sample-greeting',
        description: 'A static greeting resource for tests',
        mimeType: 'text/plain',
    )]
    public function getGreeting(): string
    {
        return 'Hello from the Mate test fixture!';
    }

    #[MateResource(
        uri: 'sample://payload',
        name: 'sample-payload',
        description: 'A resource whose body is an encoded structure',
        mimeType: 'application/json',
    )]
    public function getPayload(): string
    {
        return ResponseEncoder::encode(['answer' => 42, 'nested' => ['ok' => true]]);
    }

    /**
     * @return array{uri: string, mimeType: string, text: string}
     */
    #[MateResourceTemplate(
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
