<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\Tests\Functional\App;

use Symfony\Component\Security\Core\User\InMemoryUser;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

/**
 * Test token handler that parses scopes from the token string.
 *
 * Token format: "scope1,scope2,scope3"
 * Example: "read,write" -> ROLE_OAUTH2_READ, ROLE_OAUTH2_WRITE
 */
final class TestTokenHandler implements AccessTokenHandlerInterface
{
    public function getUserBadgeFrom(#[\SensitiveParameter] string $accessToken): UserBadge
    {
        // Support underscore, comma and space-separated scopes
        $scopes = array_filter(array_map('trim', preg_split('/[_,\s]+/', $accessToken)));
        $roles = array_map(
            fn (string $scope) => 'ROLE_OAUTH2_'.strtoupper($scope),
            $scopes
        );

        // Always add ROLE_USER
        $roles[] = 'ROLE_USER';

        return new UserBadge(
            'test-user',
            fn () => new InMemoryUser('test-user', null, array_unique($roles))
        );
    }
}
