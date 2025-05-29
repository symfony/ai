<?php

declare(strict_types=1);

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpSdk\Tests\Message;

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpSdk\Message\Response;

final class ResponseTest extends TestCase
{
    public function testWithIntegerId(): void
    {
        $response = new Response(1, ['foo' => 'bar']);
        $expected = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'result' => ['foo' => 'bar'],
        ];

        self::assertSame($expected, $response->jsonSerialize());
    }

    public function testWithStringId(): void
    {
        $response = new Response('abc', ['foo' => 'bar']);
        $expected = [
            'jsonrpc' => '2.0',
            'id' => 'abc',
            'result' => ['foo' => 'bar'],
        ];

        self::assertSame($expected, $response->jsonSerialize());
    }
}
