<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Metadata;

use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Guillaume Loulier <personal@guillaumeloulier.fr>
 */
trait IdentifierAwareTrait
{
    private AbstractUid&TimeBasedUidInterface $id;

    public function withId((AbstractUid&TimeBasedUidInterface)|null $id = null): void
    {
        $this->id = $id ?? Uuid::v7();
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }
}
