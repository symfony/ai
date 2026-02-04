<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Toolbox\Confirmation;

/**
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum PolicyDecision: string
{
    case Allow = 'allow';
    case Deny = 'deny';
    case AskUser = 'ask_user';
}
