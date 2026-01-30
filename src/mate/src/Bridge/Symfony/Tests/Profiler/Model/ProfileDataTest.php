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
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Model\ProfileData;
use Symfony\Component\HttpKernel\Profiler\Profile;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class ProfileDataTest extends TestCase
{
    public function testConstructorSetsProperties()
    {
        $profile = new Profile('abc123');
        $profile->setMethod('GET');
        $profile->setUrl('/test');

        $profileData = new ProfileData(
            profile: $profile,
            context: 'website'
        );

        $this->assertSame($profile, $profileData->profile);
        $this->assertSame('website', $profileData->context);
    }

    public function testContextCanBeNull()
    {
        $profile = new Profile('abc123');

        $profileData = new ProfileData(
            profile: $profile,
            context: null
        );

        $this->assertSame($profile, $profileData->profile);
        $this->assertNull($profileData->context);
    }

    public function testContextDefaultsToNull()
    {
        $profile = new Profile('abc123');

        $profileData = new ProfileData(
            profile: $profile
        );

        $this->assertSame($profile, $profileData->profile);
        $this->assertNull($profileData->context);
    }

    public function testPropertiesAreReadonly()
    {
        $profile = new Profile('abc123');
        $profileData = new ProfileData(
            profile: $profile,
            context: 'admin'
        );

        $reflection = new \ReflectionClass($profileData);

        $profileProperty = $reflection->getProperty('profile');
        $this->assertTrue($profileProperty->isReadOnly());

        $contextProperty = $reflection->getProperty('context');
        $this->assertTrue($contextProperty->isReadOnly());
    }

    public function testProfileMethodsAreAccessible()
    {
        $profile = new Profile('abc123');
        $profile->setMethod('POST');
        $profile->setUrl('/api/users');
        $profile->setIp('192.168.1.1');

        $profileData = new ProfileData(profile: $profile);

        $this->assertSame('abc123', $profileData->profile->getToken());
        $this->assertSame('POST', $profileData->profile->getMethod());
        $this->assertSame('/api/users', $profileData->profile->getUrl());
        $this->assertSame('192.168.1.1', $profileData->profile->getIp());
    }
}
