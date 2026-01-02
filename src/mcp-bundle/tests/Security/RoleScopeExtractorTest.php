<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Security;

use PHPUnit\Framework\TestCase;
use Symfony\AI\McpBundle\Security\RoleScopeExtractor;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

final class RoleScopeExtractorTest extends TestCase
{
    private RoleScopeExtractor $extractor;

    protected function setUp(): void
    {
        $this->extractor = new RoleScopeExtractor();
    }

    public function testExtractsOAuth2Scopes()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([
            'ROLE_OAUTH2_READ',
            'ROLE_OAUTH2_WRITE',
            'ROLE_OAUTH2_ADMIN',
        ]);

        $scopes = $this->extractor->extract($token);

        $this->assertSame(['read', 'write', 'admin'], $scopes);
    }

    public function testIgnoresNonOAuth2Roles()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([
            'ROLE_USER',
            'ROLE_ADMIN',
            'ROLE_OAUTH2_READ',
        ]);

        $scopes = $this->extractor->extract($token);

        $this->assertSame(['read'], $scopes);
    }

    public function testReturnsEmptyArrayWhenNoOAuth2Roles()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([
            'ROLE_USER',
            'ROLE_ADMIN',
        ]);

        $scopes = $this->extractor->extract($token);

        $this->assertSame([], $scopes);
    }

    public function testConvertsToLowercase()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([
            'ROLE_OAUTH2_ADMIN_WRITE',
            'ROLE_OAUTH2_SuperScope',
        ]);

        $scopes = $this->extractor->extract($token);

        $this->assertSame(['admin_write', 'superscope'], $scopes);
    }

    public function testHandlesEmptyRoles()
    {
        $token = $this->createMock(TokenInterface::class);
        $token->method('getRoleNames')->willReturn([]);

        $scopes = $this->extractor->extract($token);

        $this->assertSame([], $scopes);
    }
}
