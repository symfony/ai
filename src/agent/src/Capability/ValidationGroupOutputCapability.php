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

use Symfony\Component\Validator\Constraints\GroupSequence;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
final class ValidationGroupOutputCapability implements InputCapabilityInterface
{
    /**
     * @param string[]|GroupSequence $groups
     */
    public function __construct(
        private readonly array|GroupSequence $groups,
    ) {
    }

    /** @return string[]|GroupSequence */
    public function getGroups(): array|GroupSequence
    {
        return $this->groups;
    }
}
