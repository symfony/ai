<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Execution;

/**
 * Discriminator for the updates yielded by an agent {@see Execution}.
 *
 * @author Christopher Hertel <mail@christopher-hertel.de>
 */
enum UpdateType: string
{
    case Progress = 'progress';
    case Result = 'result';
}
