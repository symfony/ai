<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\MiniMax\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Bridge\MiniMax\Factory;
use Symfony\AI\Platform\Bridge\MiniMax\ModelCatalog;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\JsonMockResponse;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class FactoryTest extends TestCase
{
    public function testTheJobHandleCarriesTheNameTheProviderWasCreatedWith()
    {
        $httpClient = new MockHttpClient(new JsonMockResponse(['task_id' => '789', 'base_resp' => ['status_code' => 0]]));

        $handle = Factory::createProvider('key', $httpClient, 'https://api.minimax.io/v1', new ModelCatalog(), null, null, 'minimax-eu')
            ->invoke('MiniMax-Hailuo-02', 'A cat playing piano')
            ->asJob();

        $this->assertSame('789', $handle->getId());
        $this->assertSame('minimax-eu', $handle->getProvider());
    }
}
