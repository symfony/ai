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

use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Metadata\Metadata;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
interface MessageInterface
{
    public function getRole(): Role;

    public function getId(): AbstractUid&TimeBasedUidInterface;

    /**
     * @return string|ContentInterface[]|null
     */
    public function getContent(): string|array|null;

    public function getMetadata(): Metadata;
}
