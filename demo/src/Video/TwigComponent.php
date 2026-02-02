<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Video;

use Symfony\AI\Platform\Message\Content\Image;
use Symfony\AI\Platform\Message\Message;
use Symfony\AI\Platform\Message\MessageBag;
use Symfony\AI\Platform\PlatformInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent('video')]
final class TwigComponent
{
    use DefaultActionTrait;

    #[LiveProp]
    public string $caption = 'Please define an instruction and hit submit.';

    #[LiveProp(writable: true)]
    public ?string $instruction = null;

    #[LiveProp(writable: true)]
    public ?string $image = null;

    public function __construct(
        #[Autowire(service: 'ai.platform.openai')]
        private readonly PlatformInterface $platform,
    ) {
    }

    #[LiveAction]
    public function submit(): void
    {
        if (null === $this->instruction || '' === trim($this->instruction) || null === $this->image || '' === trim($this->image)) {
            $this->caption = 'Please provide both an instruction and activate your webcam.';

            return;
        }

        $messageBag = new MessageBag(
            Message::forSystem(<<<PROMPT
                You are a video captioning assistant. You are provided with a video frame and an instruction.
                You must generate a caption or answer based on the provided video frame and the user's instruction.
                You are not in a conversation with the user and there will be no follow-up questions or messages.
                PROMPT),
            Message::ofUser($this->instruction, Image::fromDataUrl($this->image))
        );

        $result = $this->platform->invoke('gpt-4o-mini', $messageBag, [
            'max_output_tokens' => 100,
        ]);

        $this->caption = $result->asText();

        $this->instruction = null;
        $this->image = null;
    }
}
