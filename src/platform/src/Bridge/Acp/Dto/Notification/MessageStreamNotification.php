<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Symfony\AI\Platform\Bridge\Acp\Dto\Notification;

use Symfony\AI\Platform\Bridge\Acp\Dto\Message\AbstractJsonRpcNotification;
use Symfony\AI\Platform\Result\Stream\Delta\DeltaInterface;
use Symfony\AI\Platform\Result\Stream\Delta\TextDelta;
use Symfony\AI\Platform\Result\Stream\Delta\ThinkingDelta;

/**
 * JSON-RPC notification: agent/messageStream.
 */
final class MessageStreamNotification extends AbstractJsonRpcNotification
{
    public string $method = 'agent/messageStream';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $content = null;

    /**
     * @param array<string, mixed> $data
     */
    public function setContent(array $data): void
    {
        $this->content = $data;
    }

    public function toDelta(): ?DeltaInterface
    {
        $type = $this->content ? ($this->content['type'] ?? 'text') : 'text';
        $text = $this->content ? ($this->content['text'] ?? '') : '';

        return match ($type) {
            'text' => new TextDelta($text),
            'thought' => new ThinkingDelta($text),
            default => null,
        };
    }
}
