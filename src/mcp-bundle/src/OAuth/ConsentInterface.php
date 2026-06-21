<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\McpBundle\OAuth;

use League\Bundle\OAuth2ServerBundle\Event\AuthorizationRequestResolveEvent;

/**
 * Strategy deciding whether an authenticated user must explicitly consent to an
 * authorization request. Implementations resolve the request directly — call
 * {@see AuthorizationRequestResolveEvent::resolveAuthorization()} to approve or
 * deny, or {@see AuthorizationRequestResolveEvent::setResponse()} to render a
 * consent screen. The firewall user is already bound when this is invoked.
 *
 * @author Johannes Wachter <johannes@sulu.io>
 */
interface ConsentInterface
{
    public function decide(AuthorizationRequestResolveEvent $event): void;
}
