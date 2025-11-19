<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Agent\MockResponse;
use Symfony\AI\Platform\Result\TextResult;

final class MockResponseTest extends TestCase
{
    public function testConstructorWithContent()
    {
        $response = new MockResponse('Test content');

        $this->assertSame('Test content', $response->getContent());
    }

    public function testConstructorWithEmptyContent()
    {
        $response = new MockResponse();

        $this->assertSame('', $response->getContent());
    }

    public function testToResult()
    {
        $response = new MockResponse('Response content');
        $result = $response->toResult();

        $this->assertInstanceOf(TextResult::class, $result);
        $this->assertSame('Response content', $result->getContent());
    }

    public function testCreate()
    {
        $response = MockResponse::create('Static created content');

        $this->assertInstanceOf(MockResponse::class, $response);
        $this->assertSame('Static created content', $response->getContent());
    }

    public function testCreateWithEmptyString()
    {
        $response = MockResponse::create('');

        $this->assertInstanceOf(MockResponse::class, $response);
        $this->assertSame('', $response->getContent());
    }
}
