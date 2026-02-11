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
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class AssistantMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    /**
     * @param ?ToolCall[] $toolCalls
     */
    public function __construct(
        private readonly ?string $content = null,
        private readonly ?array $toolCalls = null,
        private readonly ?string $thinkingContent = null,
        private readonly ?string $thinkingSignature = null,
    ) {
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::Assistant;
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

    public function hasThinkingContent(): bool
    {
        return null !== $this->thinkingContent;
    }

    public function getThinkingContent(): ?string
    {
        return $this->thinkingContent;
    }

    public function getThinkingSignature(): ?string
    {
        return $this->thinkingSignature;
    }
}
