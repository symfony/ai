<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Tests\Result;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Platform\Result\WebSearchResult;

final class WebSearchResultTest extends TestCase
{
    public function testGetters()
    {
        $result = new WebSearchResult(
            'latest AI news',
            'ws_1',
            'completed',
            ['latest AI news', 'OpenAI recent announcements'],
            'provider-signature',
        );

        $this->assertSame('latest AI news', $result->getContent());
        $this->assertSame('latest AI news', $result->getQuery());
        $this->assertSame('ws_1', $result->getId());
        $this->assertSame('completed', $result->getStatus());
        $this->assertSame(['latest AI news', 'OpenAI recent announcements'], $result->getQueries());
        $this->assertSame('provider-signature', $result->getSignature());
    }

    public function testDefaultsToNull()
    {
        $result = new WebSearchResult();

        $this->assertNull($result->getContent());
        $this->assertNull($result->getQuery());
        $this->assertNull($result->getId());
        $this->assertNull($result->getStatus());
        $this->assertSame([], $result->getQueries());
        $this->assertNull($result->getSignature());
    }
}
