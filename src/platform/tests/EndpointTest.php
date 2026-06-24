<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Endpoint;
use Symfony\AI\Platform\Exception\InvalidArgumentException;

final class EndpointTest extends TestCase
{
    public function testReturnsContract()
    {
        $endpoint = new Endpoint('anthropic.messages');

        $this->assertSame('anthropic.messages', $endpoint->getContract());
    }

    public function testReturnsDefaults()
    {
        $defaults = ['temperature' => 0.7, 'max_tokens' => 1024];
        $endpoint = new Endpoint('openai.chat_completions', $defaults);

        $this->assertSame($defaults, $endpoint->getDefaults());
    }

    public function testReturnsEmptyDefaultsByDefault()
    {
        $endpoint = new Endpoint('openai.responses');

        $this->assertSame([], $endpoint->getDefaults());
    }

    public function testThrowsOnEmptyContract()
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Endpoint contract cannot be empty.');

        new Endpoint('   ');
    }
}
