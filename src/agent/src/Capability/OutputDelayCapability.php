<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Agent\Capability;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class OutputDelayCapability implements OutputCapabilityInterface
{
    use DelayCapabilityTrait;
}
