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
final class AssistantMessage implements MessageInterface
{
    use MetadataAwareTrait;

    private readonly AbstractUid&TimeBasedUidInterface $id;

    /**
     * @param ?ToolCall[] $toolCalls
     */
    public function __construct(
        private ?string $content = null,
        private ?array $toolCalls = null,
    ) {
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::Assistant;
    }

    public function getId(): AbstractUid&TimeBasedUidInterface
    {
        return $this->id;
    }

    public function hasToolCalls(): bool
    {
        return null !== $this->toolCalls && [] !== $this->toolCalls;
    }

    /**
     * @return ?ToolCall[]
     */
    public function getToolCalls(): ?array
    {
        return $this->toolCalls;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }
}
