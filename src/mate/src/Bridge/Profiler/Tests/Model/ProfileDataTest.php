<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Profiler\Tests\Model;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Profiler\Model\ProfileData;
use Symfony\AI\Mate\Bridge\Profiler\Model\ProfileIndex;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfileDataTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $collectors = ['request' => ['method' => 'GET']];
        $children = ['child1', 'child2'];

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: $collectors,
            children: $children
        );

        $this->assertSame('abc123', $profileData->token);
        $this->assertSame($index, $profileData->index);
        $this->assertSame($collectors, $profileData->collectors);
        $this->assertSame($children, $profileData->children);
    }

    public function testHasCollectorReturnsTrueForExistingCollector()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: ['request' => [], 'response' => []],
            children: []
        );

        $this->assertTrue($profileData->hasCollector('request'));
        $this->assertTrue($profileData->hasCollector('response'));
    }

    public function testHasCollectorReturnsFalseForNonExistingCollector()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: ['request' => []],
            children: []
        );

        $this->assertFalse($profileData->hasCollector('nonexistent'));
    }

    public function testGetCollectorReturnsCollectorData()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $requestData = ['method' => 'GET', 'path' => '/test'];
        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: ['request' => $requestData],
            children: []
        );

        $this->assertSame($requestData, $profileData->getCollector('request'));
    }

    public function testGetCollectorReturnsNullForNonExistingCollector()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: [],
            children: []
        );

        $this->assertNull($profileData->getCollector('nonexistent'));
    }

    public function testChildrenCanBeEmpty()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: [],
            children: []
        );

        $this->assertIsArray($profileData->children);
        $this->assertEmpty($profileData->children);
    }

    public function testCollectorsCanBeEmpty()
    {
        $index = new ProfileIndex(
            token: 'abc123',
            ip: '127.0.0.1',
            method: 'GET',
            url: '/test',
            time: 1234567890
        );

        $profileData = new ProfileData(
            token: 'abc123',
            index: $index,
            collectors: [],
            children: []
        );

        $this->assertIsArray($profileData->collectors);
        $this->assertEmpty($profileData->collectors);
    }
}
