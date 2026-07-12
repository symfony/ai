<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use Symfony\AI\AiBundle\Test\AiAssertionsTrait;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * The assertions of the AI bundle against the profiler of a real request - the home page calls no
 * platform, which keeps this test free of an API key, and part of the default suite.
 *
 * The use cases themselves are driven by the browser tests in tests/E2E, which do call the models.
 */
#[CoversNothing]
final class AiAssertionsTest extends WebTestCase
{
    use AiAssertionsTrait;

    public function testTheHomePageCallsNoPlatform()
    {
        $client = static::createClient();
        $client->enableProfiler();

        $client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertPlatformCallCount(0);
        self::assertToolCallCount(0);
    }

    public function testTheToolsOfTheAgentsAreRegistered()
    {
        $client = static::createClient();
        $client->enableProfiler();

        $client->request('GET', '/');

        // The collector gathers the tools of every toolbox of the application, so they are all
        // registered - even on a request that called no agent at all.
        self::assertToolRegistered('similarity_search');
        self::assertToolRegistered('movie_search');
        self::assertToolRegistered('symfony_blog');
    }
}
