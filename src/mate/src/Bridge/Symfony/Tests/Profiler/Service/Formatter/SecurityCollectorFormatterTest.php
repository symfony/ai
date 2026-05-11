<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Mate\Bridge\Symfony\Tests\Profiler\Service\Formatter;

use PHPUnit\Framework\TestCase;
use Symfony\AI\Mate\Bridge\Symfony\Profiler\Service\Formatter\SecurityCollectorFormatter;
use Symfony\Bundle\SecurityBundle\DataCollector\SecurityDataCollector;
use Symfony\Component\VarDumper\Cloner\Data;

/**
 * @author Johannes Wachter <johannes@sulu.io>
 */
final class SecurityCollectorFormatterTest extends TestCase
{
    private SecurityCollectorFormatter $formatter;

    protected function setUp(): void
    {
        $this->formatter = new SecurityCollectorFormatter();
    }

    public function testGetName()
    {
        $this->assertSame('security', $this->formatter->getName());
    }

    public function testFormatWhenDisabled()
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(false);
        $collector->method('isAuthenticated')->willReturn(false);
        $collector->method('getUser')->willReturn('');
        $collector->method('getRoles')->willReturn([]);
        $collector->method('getInheritedRoles')->willReturn([]);
        $collector->method('supportsRoleHierarchy')->willReturn(false);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getImpersonatorUser')->willReturn(null);
        $collector->method('getVoterStrategy')->willReturn('');
        $collector->method('getVoters')->willReturn([]);
        $collector->method('getAccessDecisionLog')->willReturn([]);
        $collector->method('getFirewall')->willReturn(null);

        $result = $this->formatter->format($collector);

        $this->assertFalse($result['enabled']);
        $this->assertFalse($result['authenticated']);
        $this->assertNull($result['firewall']);
        $this->assertFalse($result['access_decision_log_truncated']);
        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('logout_url', $result);
    }

    public function testFormatWhenAuthenticated()
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('admin@example.com');
        $collector->method('getRoles')->willReturn(['ROLE_ADMIN', 'ROLE_USER']);
        $collector->method('getInheritedRoles')->willReturn(['ROLE_USER']);
        $collector->method('supportsRoleHierarchy')->willReturn(true);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getImpersonatorUser')->willReturn(null);
        $collector->method('getVoterStrategy')->willReturn('affirmative');
        $collector->method('getVoters')->willReturn(['App\\Security\\Voter\\PostVoter']);
        $collector->method('getAccessDecisionLog')->willReturn([]);
        $collector->method('getFirewall')->willReturn(['name' => 'main', 'security' => true]);

        $result = $this->formatter->format($collector);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['authenticated']);
        $this->assertSame('admin@example.com', $result['user']);
        $this->assertSame(['ROLE_ADMIN', 'ROLE_USER'], $result['roles']);
        $this->assertSame(['ROLE_USER'], $result['inherited_roles']);
        $this->assertTrue($result['supports_role_hierarchy']);
        $this->assertFalse($result['impersonated']);
        $this->assertNull($result['impersonator_user']);
        $this->assertSame('affirmative', $result['voter_strategy']);
        $this->assertSame(['App\\Security\\Voter\\PostVoter'], $result['voters']);
        $this->assertSame(['name' => 'main', 'security' => true], $result['firewall']);
    }

    public function testFormatHandlesDataObjectsForRolesAndVoters()
    {
        $rolesData = $this->createMock(Data::class);
        $rolesData->method('getValue')->with(true)->willReturn(['ROLE_USER']);

        $inheritedRolesData = $this->createMock(Data::class);
        $inheritedRolesData->method('getValue')->with(true)->willReturn([]);

        $votersData = $this->createMock(Data::class);
        $votersData->method('getValue')->with(true)->willReturn(['App\\Security\\Voter\\MyVoter']);

        $firewallData = $this->createMock(Data::class);
        $firewallData->method('getValue')->with(true)->willReturn(['name' => 'main']);

        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user');
        $collector->method('getRoles')->willReturn($rolesData);
        $collector->method('getInheritedRoles')->willReturn($inheritedRolesData);
        $collector->method('supportsRoleHierarchy')->willReturn(true);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getImpersonatorUser')->willReturn(null);
        $collector->method('getVoterStrategy')->willReturn('unanimous');
        $collector->method('getVoters')->willReturn($votersData);
        $collector->method('getAccessDecisionLog')->willReturn([]);
        $collector->method('getFirewall')->willReturn($firewallData);

        $result = $this->formatter->format($collector);

        $this->assertSame(['ROLE_USER'], $result['roles']);
        $this->assertSame(['App\\Security\\Voter\\MyVoter'], $result['voters']);
        $this->assertSame(['name' => 'main'], $result['firewall']);
    }

    public function testFormatWhenImpersonated()
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('target_user');
        $collector->method('getRoles')->willReturn([]);
        $collector->method('getInheritedRoles')->willReturn([]);
        $collector->method('supportsRoleHierarchy')->willReturn(false);
        $collector->method('isImpersonated')->willReturn(true);
        $collector->method('getImpersonatorUser')->willReturn('admin@example.com');
        $collector->method('getVoterStrategy')->willReturn('affirmative');
        $collector->method('getVoters')->willReturn([]);
        $collector->method('getAccessDecisionLog')->willReturn([]);
        $collector->method('getFirewall')->willReturn(null);

        $result = $this->formatter->format($collector);

        $this->assertTrue($result['impersonated']);
        $this->assertSame('admin@example.com', $result['impersonator_user']);
    }

    public function testFormatTruncatesAccessDecisionLogAt50()
    {
        $log = [];
        for ($i = 0; $i < 51; ++$i) {
            $log[] = ['attribute' => 'ROLE_ADMIN', 'object' => null, 'result' => true, 'voter_details' => []];
        }

        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user');
        $collector->method('getRoles')->willReturn([]);
        $collector->method('getInheritedRoles')->willReturn([]);
        $collector->method('supportsRoleHierarchy')->willReturn(false);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getImpersonatorUser')->willReturn(null);
        $collector->method('getVoterStrategy')->willReturn('affirmative');
        $collector->method('getVoters')->willReturn([]);
        $collector->method('getAccessDecisionLog')->willReturn($log);
        $collector->method('getFirewall')->willReturn(null);

        $result = $this->formatter->format($collector);

        $this->assertCount(50, $result['access_decision_log']);
        $this->assertTrue($result['access_decision_log_truncated']);
    }

    public function testFormatDoesNotExposeTokenOrLogoutUrl()
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('user');
        $collector->method('getRoles')->willReturn([]);
        $collector->method('getInheritedRoles')->willReturn([]);
        $collector->method('supportsRoleHierarchy')->willReturn(false);
        $collector->method('isImpersonated')->willReturn(false);
        $collector->method('getImpersonatorUser')->willReturn(null);
        $collector->method('getVoterStrategy')->willReturn('affirmative');
        $collector->method('getVoters')->willReturn([]);
        $collector->method('getAccessDecisionLog')->willReturn([]);
        $collector->method('getFirewall')->willReturn(null);

        $result = $this->formatter->format($collector);

        $this->assertArrayNotHasKey('token', $result);
        $this->assertArrayNotHasKey('token_class', $result);
        $this->assertArrayNotHasKey('logout_url', $result);
    }

    public function testGetSummary()
    {
        $collector = $this->createMock(SecurityDataCollector::class);
        $collector->method('isEnabled')->willReturn(true);
        $collector->method('isAuthenticated')->willReturn(true);
        $collector->method('getUser')->willReturn('admin');
        $collector->method('getRoles')->willReturn(['ROLE_ADMIN']);
        $collector->method('isImpersonated')->willReturn(false);

        $result = $this->formatter->getSummary($collector);

        $this->assertTrue($result['enabled']);
        $this->assertTrue($result['authenticated']);
        $this->assertSame('admin', $result['user']);
        $this->assertSame(['ROLE_ADMIN'], $result['roles']);
        $this->assertFalse($result['impersonated']);
        $this->assertArrayNotHasKey('voters', $result);
        $this->assertArrayNotHasKey('firewall', $result);
        $this->assertArrayNotHasKey('access_decision_log', $result);
    }
}
