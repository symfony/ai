<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Model;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileIndex;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfileIndexTest extends TestCase
{
    public function testToArrayReturnsCorrectStructure()
    {
        $profile = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/api/users',
            time: 1704448800,
            statusCode: 200,
            parentToken: null,
            context: null,
            type: 'request',
        );

        $array = $profile->toArray();

        $this->assertSame('abc123', $array['token']);
        $this->assertSame('127.0.0.1', $array['ip']);
        $this->assertSame('GET', $array['method']);
        $this->assertSame('/api/users', $array['url']);
        $this->assertSame(1704448800, $array['time']);
        $this->assertIsString($array['time_formatted']);
        $this->assertSame(200, $array['status_code']);
        $this->assertNull($array['parent_token']);
        $this->assertArrayNotHasKey('context', $array);
    }

    public function testToArrayIncludesContextWhenPresent()
    {
        $profile = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/api/users',
            time: 1704448800,
            statusCode: 200,
            parentToken: null,
            context: 'test-context',
            type: 'request',
        );

        $array = $profile->toArray();

        $this->assertArrayHasKey('context', $array);
        $this->assertSame('test-context', $array['context']);
    }

    public function testToArrayFormatsTimeCorrectly()
    {
        $timestamp = 1704448800;
        $profile = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/api/users',
            time: $timestamp,
        );

        $array = $profile->toArray();

        $this->assertSame(date(\DateTimeInterface::ATOM, $timestamp), $array['time_formatted']);
    }
}
