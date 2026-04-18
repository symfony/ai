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
use Symfony\AI\Platform\Result\MultiPartResult;
use Symfony\AI\Platform\Result\ResultInterface;
use Symfony\AI\Platform\Result\ThinkingResult;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\AI\Platform\Result\ToolCallResult;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class AssistantMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    public function __construct(
        private readonly ResultInterface $content,
    ) {
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::Assistant;
    }

    public function hasToolCalls(): bool
    {
        if ($this->content instanceof MultiPartResult) {
            return array_any($this->content->getContent(), static fn (ResultInterface $part) => $part instanceof ToolCallResult);
        }

        return $this->content instanceof ToolCallResult;
    }

    /**
     * @return ToolCall[]|null
     */
    public function getToolCalls(): ?array
    {
        if ($this->content instanceof MultiPartResult) {
            $toolCalls = [];
            foreach ($this->content as $part) {
                if ($part instanceof ToolCallResult) {
                    array_push($toolCalls, ...$part->getContent());
                }
            }

            return $toolCalls ?: null;
        }

        if ($this->content instanceof ToolCallResult) {
            return $this->content->getContent();
        }

        return null;
    }

    public function getContent(): ResultInterface
    {
        return $this->content;
    }

    public function hasThinking(): bool
    {
        return match (true) {
            $this->content instanceof MultiPartResult => array_any($this->content->getContent(), static fn (ResultInterface $part) => $part instanceof ThinkingResult),
            $this->content instanceof ThinkingResult => true,
            default => false,
        };
    }

    public function getThinking(): ?ThinkingResult
    {
        return match (true) {
            $this->content instanceof MultiPartResult => array_find($this->content->getContent(), static fn (ResultInterface $part) => $part instanceof ThinkingResult),
            $this->content instanceof ThinkingResult => $this->content,
            default => null,
        };
    }
}
