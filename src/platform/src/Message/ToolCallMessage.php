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
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\AbstractUid;
use Symfony\Component\Uid\TimeBasedUidInterface;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ToolCallMessage implements MessageInterface
{
    use MetadataAwareTrait;

    private readonly AbstractUid&TimeBasedUidInterface $id;

    public function __construct(
        private readonly ToolCall $toolCall,
        private readonly string $content,
    ) {
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::ToolCall;
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    public function getContent(): string
    {
        return $this->content;
    }
}
