<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Bridge\Decart;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\Decart\Decart;
use Symfony\AI\Platform\Bridge\Decart\DecartClient;
use Symfony\AI\Platform\Model;
use Symfony\Component\HttpClient\MockHttpClient;

final class DecartClientTest extends TestCase
{
    public function testSupportsModel()
    {
        $client = new DecartClient(
            new MockHttpClient(),
            'my-api-key',
        );

        $this->assertTrue($client->supports(new Decart('lucy-dev-i2v')));
        $this->assertFalse($client->supports(new Model('any-model')));
    }
}
