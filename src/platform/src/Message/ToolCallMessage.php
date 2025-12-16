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

use Symfony\AI\Platform\Message\Content\Audio;
use Symfony\AI\Platform\Message\Content\ContentInterface;
use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Content\Text;
use Symfony\AI\Platform\Metadata\MetadataAwareTrait;
use Symfony\AI\Platform\Result\ToolCall;
use Symfony\Component\Uid\Uuid;

/**
 * @author Denis Zunke <denis.zunke@gmail.com>
 */
final class ToolCallMessage implements MessageInterface
{
    use IdentifierAwareTrait;
    use MetadataAwareTrait;

    /**
     * @var ContentInterface[]
     */
    private readonly array $content;

    public function __construct(
        private readonly ToolCall $toolCall,
        ContentInterface|string ...$content,
    ) {
        $this->content = array_map(
            static fn (ContentInterface|string $c): ContentInterface => \is_string($c) ? new Text($c) : $c,
            $content,
        );
        $this->id = Uuid::v7();
    }

    public function getRole(): Role
    {
        return Role::ToolCall;
    }

    public function getToolCall(): ToolCall
    {
        return $this->toolCall;
    }

    /**
     * @return ContentInterface[]
     */
    public function getContent(): array
    {
        return $this->content;
    }

    public function hasImageContent(): bool
    {
        foreach ($this->content as $content) {
            if ($content instanceof Image) {
                return true;
            }
        }

        return false;
    }

    public function hasAudioContent(): bool
    {
        foreach ($this->content as $content) {
            if ($content instanceof Audio) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get all text content as a single string.
     */
    public function asText(): ?string
    {
        $texts = [];
        foreach ($this->content as $content) {
            if ($content instanceof Text) {
                $texts[] = $content->getText();
            }
        }

        if ([] === $texts) {
            return null;
        }

        return implode("\n", $texts);
    }
}
