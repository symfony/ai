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

use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class SystemMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    public function __construct(
        private readonly string|Template $content,
    ) {
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::System;
    }

    public function getContent(): string|Template
    {
        return $this->content;
    }
}
