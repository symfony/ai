<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Message;

use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait IdentifierAwareTrait
{
    private AbstractUid&TimeBasedUidInterface $id;

    public function withId(AbstractUid&TimeBasedUidInterface $id): self
    {
        $clone = clone $this;
        $clone->id = $id;

        return $clone;
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }
}
